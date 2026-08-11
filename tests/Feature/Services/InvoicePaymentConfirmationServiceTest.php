<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Exceptions\ConfirmedAmountMismatchException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\LatePaymentAmountConflictException;
use App\Exceptions\LatePaymentReasonRequiredException;
use App\Jobs\SendCallbackJob;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Models\User;
use App\Services\InvoicePaymentConfirmationService;
use App\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $this->merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $this->project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $this->merchant->id,
        'invoice_ttl_minutes' => 30,
        'unique_code_min' => 1,
        'unique_code_max' => 999,
    ]);

    $this->admin = User::factory()->create();
    $this->service = app(InvoicePaymentConfirmationService::class);
});

function makeInvoiceForConfirmation(string $externalId = 'ORDER-1', int $amount = 100000): Invoice
{
    return app(InvoiceService::class)->create(test()->project, [
        'external_id' => $externalId,
        'amount' => $amount,
    ]);
}

it('confirms a waiting_payment invoice and records paid_at, confirmed_at, confirmed_by', function () {
    $invoice = makeInvoiceForConfirmation();
    $paidAt = Carbon::parse('2026-08-10 10:00:00');

    $confirmed = $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => $paidAt,
    ]);

    expect($confirmed->status)->toBe(InvoiceStatus::Paid)
        ->and($confirmed->paid_at->equalTo($paidAt))->toBeTrue()
        ->and($confirmed->confirmed_by)->toBe($this->admin->id)
        ->and($confirmed->confirmed_at)->not->toBeNull()
        ->and($confirmed->confirmation_reference)->toBe('RRN123456');
});

it('confirms a manual_verification invoice', function () {
    $invoice = makeInvoiceForConfirmation();
    $invoice->forceFill(['status' => InvoiceStatus::ManualVerification])->save();

    $confirmed = $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
    ]);

    expect($confirmed->status)->toBe(InvoiceStatus::Paid);
});

it('writes an audit log with event invoice.paid on a normal confirm', function () {
    $invoice = makeInvoiceForConfirmation();

    $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
    ]);

    $log = AuditLog::where('auditable_id', $invoice->id)->where('event', 'invoice.paid')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->new_values['late_payment'])->toBeFalse();
});

it('rejects confirmation when confirmed_amount does not match payment_amount', function () {
    $invoice = makeInvoiceForConfirmation();

    $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount + 1,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
    ]);
})->throws(ConfirmedAmountMismatchException::class);

it('rejects confirmation for a terminal invoice', function () {
    $invoice = makeInvoiceForConfirmation();
    $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

    $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
    ]);
})->throws(InvalidStatusTransitionException::class);

it('confirms an expired invoice as a late payment when the amount is free', function () {
    $invoice = makeInvoiceForConfirmation();
    $invoice->forceFill(['status' => InvoiceStatus::Expired])->save();

    $confirmed = $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
        'confirmation_note' => 'Customer transfer 2 menit setelah kedaluwarsa, mutasi sudah dicek manual.',
    ]);

    expect($confirmed->status)->toBe(InvoiceStatus::Paid);

    $log = AuditLog::where('auditable_id', $invoice->id)->where('event', 'invoice.paid.late_payment')->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['late_payment'])->toBeTrue();
});

it('rejects a late payment confirmation without a confirmation_note', function () {
    $invoice = makeInvoiceForConfirmation();
    $invoice->forceFill(['status' => InvoiceStatus::Expired])->save();

    $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
    ]);
})->throws(LatePaymentReasonRequiredException::class);

it('rejects late payment confirmation when another active invoice now holds the same payment_amount', function () {
    $invoice = makeInvoiceForConfirmation('ORDER-1', 100000);
    $invoice->forceFill(['status' => InvoiceStatus::Expired])->save();

    // Nominal yang sama sekarang dipakai invoice lain yang masih aktif.
    $conflicting = makeInvoiceForConfirmation('ORDER-2', 500000);
    $conflicting->forceFill(['payment_amount' => $invoice->payment_amount])->save();

    $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
        'confirmation_note' => 'Pembayaran terlambat.',
    ]);
})->throws(LatePaymentAmountConflictException::class);

it('dispatches SendCallbackJob for invoice.paid after a successful confirm', function () {
    Queue::fake();

    $invoice = makeInvoiceForConfirmation();

    $this->service->confirm($invoice, $this->admin, [
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now(),
    ]);

    Queue::assertPushed(SendCallbackJob::class, fn (SendCallbackJob $job): bool => $job->invoiceId === $invoice->id && $job->eventType === 'invoice.paid');
});

it('does not modify an already paid invoice on a rejected retry', function () {
    $invoice = makeInvoiceForConfirmation();
    $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now(), 'confirmation_reference' => 'ORIGINAL'])->save();

    try {
        $this->service->confirm($invoice, $this->admin, [
            'confirmed_amount' => $invoice->payment_amount,
            'confirmation_reference' => 'ATTEMPTED-OVERWRITE',
            'paid_at' => now(),
        ]);
    } catch (InvalidStatusTransitionException) {
        // expected
    }

    expect($invoice->fresh()->confirmation_reference)->toBe('ORIGINAL');
});
