<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\QrisTestPayload;

it('encrypts static_payload at rest but exposes it decrypted through the model', function () {
    $raw = QrisTestPayload::static();

    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant - Toko A',
        'static_payload' => $raw,
        'currency' => '360',
        'is_active' => true,
    ]);

    $columnOnDisk = DB::table('qris_merchants')->where('id', $merchant->id)->value('static_payload');

    expect($columnOnDisk)->not->toBe($raw)
        ->and($merchant->fresh()->static_payload)->toBe($raw);
});

it('encrypts signing_secret at rest and hides it from serialization', function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $merchant->id,
    ]);

    $apiKey = ApiKey::create([
        'project_id' => $project->id,
        'name' => 'Production',
        'key_prefix' => 'qris_live_9f3a2c',
        'key_hash' => hash('sha256', 'secret-api-key'),
        'signing_secret' => 'super-secret-hmac-key',
    ]);

    $columnOnDisk = DB::table('api_keys')->where('id', $apiKey->id)->value('signing_secret');

    expect($columnOnDisk)->not->toBe('super-secret-hmac-key')
        ->and($apiKey->fresh()->signing_secret)->toBe('super-secret-hmac-key')
        ->and($apiKey->fresh()->toArray())->not->toHaveKeys(['signing_secret', 'key_hash']);
});

it('encrypts callback_signing_secret at rest and hides it from serialization', function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $merchant->id,
        'callback_signing_secret' => 'super-secret-callback-key',
    ]);

    $columnOnDisk = DB::table('projects')->where('id', $project->id)->value('callback_signing_secret');

    expect($columnOnDisk)->not->toBe('super-secret-callback-key')
        ->and($project->fresh()->callback_signing_secret)->toBe('super-secret-callback-key')
        ->and($project->fresh()->toArray())->not->toHaveKey('callback_signing_secret');
});

it('casts invoice status to the InvoiceStatus enum and hides qris_payload from serialization', function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $merchant->id,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-20260806-0001',
        'project_id' => $project->id,
        'qris_merchant_id' => $merchant->id,
        'external_id' => 'ORDER-1',
        'base_amount' => 100000,
        'unique_code' => 237,
        'payment_amount' => 100237,
        'qris_payload' => 'raw-dynamic-payload-should-never-leak',
        'status' => InvoiceStatus::WaitingPayment,
    ]);

    $fresh = $invoice->fresh();

    expect($fresh->status)->toBeInstanceOf(InvoiceStatus::class)
        ->and($fresh->status)->toBe(InvoiceStatus::WaitingPayment)
        ->and($fresh->base_amount)->toBeInt()
        ->and($fresh->unique_code)->toBeInt()
        ->and($fresh->payment_amount)->toBeInt()
        ->and($fresh->toArray())->not->toHaveKey('qris_payload')
        ->and(json_encode($fresh))->not->toContain('raw-dynamic-payload-should-never-leak');
});

it('casts invoice callback_failed_at to a datetime', function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $merchant->id,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-20260806-0001',
        'project_id' => $project->id,
        'qris_merchant_id' => $merchant->id,
        'external_id' => 'ORDER-1',
        'base_amount' => 100000,
        'unique_code' => 237,
        'payment_amount' => 100237,
        'qris_payload' => 'raw-dynamic-payload-should-never-leak',
        'status' => InvoiceStatus::WaitingPayment,
        'callback_failed_at' => now(),
    ]);

    expect($invoice->fresh()->callback_failed_at)->toBeInstanceOf(Carbon::class);
});
