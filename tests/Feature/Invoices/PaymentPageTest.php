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
        'description' => 'Pembayaran pesanan',
    ]);
});

it('shows the amount and qr image for a waiting_payment invoice', function () {
    $response = $this->get("/invoice/{$this->invoice->invoice_number}");

    $response->assertOk()
        ->assertSee(number_format($this->invoice->payment_amount, 0, ',', '.'), escape: false)
        ->assertSee(route('invoices.qr', $this->invoice), escape: false);
});

it('never leaks the raw qris_payload into the page', function () {
    $response = $this->get("/invoice/{$this->invoice->invoice_number}");

    $response->assertOk()->assertDontSee($this->invoice->qris_payload, escape: false);
});

it('shows a success message for a paid invoice', function () {
    $this->invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

    $response = $this->get("/invoice/{$this->invoice->invoice_number}");

    $response->assertOk()->assertSee('Pembayaran diterima');
});

it('shows an expiry message for an expired invoice', function () {
    $this->invoice->forceFill(['status' => InvoiceStatus::Expired])->save();

    $response = $this->get("/invoice/{$this->invoice->invoice_number}");

    $response->assertOk()->assertSee('kedaluwarsa');
});

it('shows a cancellation message for a cancelled invoice', function () {
    $this->invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();

    $response = $this->get("/invoice/{$this->invoice->invoice_number}");

    $response->assertOk()->assertSee('dibatalkan');
});

it('returns 404 for a nonexistent invoice number', function () {
    $this->get('/invoice/INV-DOES-NOT-EXIST')->assertNotFound();
});

it('scopes the page lookup to invoice_number, not the numeric id', function () {
    $this->get("/invoice/{$this->invoice->id}")->assertNotFound();
});
