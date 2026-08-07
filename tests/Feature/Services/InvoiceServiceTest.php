<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Exceptions\ExternalIdConflictException;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceService;
use App\Support\Qris\QrisPayload;
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
        'callback_url' => 'https://restaurant.example.com/payment/callback',
    ]);

    $this->service = app(InvoiceService::class);
});

it('creates an invoice with a generated dynamic payload and waiting_payment status', function () {
    $invoice = $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
        'description' => 'Pembayaran pesanan',
        'customer_name' => 'Miftah',
    ]);

    expect($invoice->status)->toBe(InvoiceStatus::WaitingPayment)
        ->and($invoice->base_amount)->toBe(100000)
        ->and($invoice->payment_amount)->toBe(100000 + $invoice->unique_code)
        ->and($invoice->unique_code)->toBeGreaterThanOrEqual(1)
        ->and($invoice->unique_code)->toBeLessThanOrEqual(999)
        ->and($invoice->expires_at)->not->toBeNull()
        ->and($invoice->callback_url)->toBe('https://restaurant.example.com/payment/callback');

    $parsedPayload = QrisPayload::parse($invoice->qris_payload);

    expect($parsedPayload->amount())->toBe((string) $invoice->payment_amount)
        ->and($parsedPayload->crcIsValid())->toBeTrue();
});

it('is idempotent for the same external_id and amount', function () {
    $first = $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    $second = $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    expect($second->id)->toBe($first->id)
        ->and(Invoice::where('project_id', $this->project->id)->where('external_id', 'ORDER-1')->count())->toBe(1);
});

it('rejects a repeated external_id with a different amount', function () {
    $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 50000,
    ]);
})->throws(ExternalIdConflictException::class);

it('does not create a second invoice when the external_id conflicts', function () {
    $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    try {
        $this->service->create($this->project, [
            'external_id' => 'ORDER-1',
            'amount' => 50000,
        ]);
    } catch (ExternalIdConflictException) {
        // diharapkan
    }

    expect(Invoice::where('project_id', $this->project->id)->where('external_id', 'ORDER-1')->count())->toBe(1);
});

it('allocates distinct unique codes for two invoices with the same base amount', function () {
    $first = $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    $second = $this->service->create($this->project, [
        'external_id' => 'ORDER-2',
        'amount' => 100000,
    ]);

    expect($first->payment_amount)->not->toBe($second->payment_amount)
        ->and($first->unique_code)->not->toBe($second->unique_code);
});

it('writes an audit log entry when an invoice is created', function () {
    $invoice = $this->service->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    $log = AuditLog::where('auditable_type', Invoice::class)
        ->where('auditable_id', $invoice->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('invoice.created')
        ->and($log->new_values['status'])->toBe(InvoiceStatus::WaitingPayment->value);
});
