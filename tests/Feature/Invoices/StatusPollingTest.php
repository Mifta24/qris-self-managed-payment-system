<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceService;
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

    $this->invoice = app(InvoiceService::class)->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);
});

it('returns only status, expires_at, and paid_at for polling', function () {
    $response = $this->getJson("/invoice/{$this->invoice->invoice_number}/status");

    $response->assertOk()
        ->assertExactJson([
            'status' => InvoiceStatus::WaitingPayment->value,
            'expires_at' => $this->invoice->expires_at->toIso8601String(),
            'paid_at' => null,
        ]);
});

it('does not require any authentication header', function () {
    $this->getJson("/invoice/{$this->invoice->invoice_number}/status")->assertOk();
});

it('returns 404 for a nonexistent invoice number', function () {
    $this->getJson('/invoice/INV-DOES-NOT-EXIST/status')->assertNotFound();
});

it('reflects a paid status once the invoice is confirmed', function () {
    $paidAt = now();
    $this->invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => $paidAt])->save();

    $response = $this->getJson("/invoice/{$this->invoice->invoice_number}/status");

    $response->assertOk()
        ->assertJsonPath('status', InvoiceStatus::Paid->value)
        ->assertJsonPath('paid_at', $paidAt->toIso8601String());
});

it('lazily expires a waiting_payment invoice whose expires_at has already passed', function () {
    $this->invoice->forceFill(['expires_at' => now()->subMinute()])->save();

    $response = $this->getJson("/invoice/{$this->invoice->invoice_number}/status");

    $response->assertOk()->assertJsonPath('status', InvoiceStatus::Expired->value);
    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Expired);
});
