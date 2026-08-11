<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Jobs\SendCallbackJob;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceExpiryService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $this->project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $merchant->id,
        'invoice_ttl_minutes' => 30,
        'unique_code_min' => 1,
        'unique_code_max' => 999,
    ]);

    $this->service = app(InvoiceExpiryService::class);
});

function makeInvoiceForExpiry(string $externalId = 'ORDER-1', int $amount = 100000): Invoice
{
    return app(InvoiceService::class)->create(test()->project, [
        'external_id' => $externalId,
        'amount' => $amount,
    ]);
}

it('expires a waiting_payment invoice whose expires_at is in the past', function () {
    $invoice = makeInvoiceForExpiry();
    $invoice->forceFill(['expires_at' => now()->subMinute()])->save();

    $expired = $this->service->expireIfDue($invoice);

    expect($expired->status)->toBe(InvoiceStatus::Expired)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Expired);

    $log = AuditLog::where('auditable_id', $invoice->id)->where('event', 'invoice.expired')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBe(['status' => InvoiceStatus::WaitingPayment->value])
        ->and($log->new_values)->toBe(['status' => InvoiceStatus::Expired->value])
        ->and($log->user_id)->toBeNull()
        ->and($log->api_key_id)->toBeNull();
});

it('does not touch a waiting_payment invoice whose expires_at is still in the future', function () {
    $invoice = makeInvoiceForExpiry();

    $result = $this->service->expireIfDue($invoice);

    expect($result->status)->toBe(InvoiceStatus::WaitingPayment);
    expect(AuditLog::where('auditable_id', $invoice->id)->where('event', 'invoice.expired')->exists())->toBeFalse();
});

it('does not expire a manual_verification invoice even when expires_at is in the past', function () {
    $invoice = makeInvoiceForExpiry();
    $invoice->forceFill([
        'status' => InvoiceStatus::ManualVerification,
        'expires_at' => now()->subMinute(),
    ])->save();

    $result = $this->service->expireIfDue($invoice);

    expect($result->status)->toBe(InvoiceStatus::ManualVerification);
});

it('does not expire a paid invoice even when expires_at is in the past', function () {
    $invoice = makeInvoiceForExpiry();
    $invoice->forceFill([
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
        'expires_at' => now()->subMinute(),
    ])->save();

    $result = $this->service->expireIfDue($invoice);

    expect($result->status)->toBe(InvoiceStatus::Paid);
});

it('is idempotent: calling it twice only writes one audit log', function () {
    $invoice = makeInvoiceForExpiry();
    $invoice->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->service->expireIfDue($invoice);
    $this->service->expireIfDue($invoice->fresh());

    expect(AuditLog::where('auditable_id', $invoice->id)->where('event', 'invoice.expired')->count())->toBe(1);
});

it('dispatches SendCallbackJob for invoice.expired only on the call that actually transitions the status', function () {
    Queue::fake();

    $invoice = makeInvoiceForExpiry();
    $invoice->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->service->expireIfDue($invoice);
    $this->service->expireIfDue($invoice->fresh());

    Queue::assertPushed(SendCallbackJob::class, 1);
    Queue::assertPushed(SendCallbackJob::class, fn (SendCallbackJob $job): bool => $job->invoiceId === $invoice->id && $job->eventType === 'invoice.expired');
});
