<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Cache;
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

it('serves the qr code as an svg image', function () {
    $response = $this->get("/qris/{$this->invoice->invoice_number}.svg");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('<svg', escape: false);
});

it('caches the rendered svg by payload hash', function () {
    $cacheKey = 'qr-svg:'.hash('sha256', $this->invoice->qris_payload);

    expect(Cache::has($cacheKey))->toBeFalse();

    $this->get("/qris/{$this->invoice->invoice_number}.svg")->assertOk();

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('returns 404 for a nonexistent invoice number', function () {
    $this->get('/qris/INV-DOES-NOT-EXIST.svg')->assertNotFound();
});
