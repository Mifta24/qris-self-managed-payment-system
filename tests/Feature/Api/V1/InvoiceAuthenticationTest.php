<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Support\ApiKeyGenerator;
use Tests\Support\ApiRequestSigner;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $this->signingSecret = 'test-signing-secret-value';

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
        'is_active' => true,
    ]);

    $generated = (new ApiKeyGenerator)->generate();
    $this->apiKeyPlain = $generated['key'];

    $this->apiKey = ApiKey::create([
        'project_id' => $this->project->id,
        'name' => 'Test key',
        'key_prefix' => $generated['prefix'],
        'key_hash' => $generated['hash'],
        'signing_secret' => $this->signingSecret,
    ]);

    $this->payload = ['external_id' => 'ORDER-1', 'amount' => 100000];
});

it('rejects a request with an unknown api key', function () {
    $response = callSigned('POST', '/api/v1/invoices', $this->payload, 'qris_live_'.str_repeat('x', 32), $this->signingSecret);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_signature');
});

it('rejects a request with a wrong signature', function () {
    $response = callSigned('POST', '/api/v1/invoices', $this->payload, $this->apiKeyPlain, 'wrong-secret');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_signature');
});

it('rejects a request with a tampered body after signing', function () {
    $headers = ApiRequestSigner::headers(
        $this->apiKeyPlain,
        $this->signingSecret,
        'POST',
        '/api/v1/invoices',
        json_encode($this->payload),
    );

    $response = test()->call(
        'POST',
        '/api/v1/invoices',
        [],
        [],
        [],
        test()->transformHeadersToServerVars(array_merge($headers, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])),
        json_encode(['external_id' => 'ORDER-1', 'amount' => 999999999]),
    );

    $response->assertStatus(401)->assertJsonPath('error.code', 'invalid_signature');
});

it('rejects an expired timestamp', function () {
    $response = callSigned(
        'POST',
        '/api/v1/invoices',
        $this->payload,
        $this->apiKeyPlain,
        $this->signingSecret,
        timestamp: now()->subMinutes(10)->getTimestamp(),
    );

    $response->assertStatus(401)->assertJsonPath('error.code', 'invalid_timestamp');
});

it('rejects a revoked api key', function () {
    $this->apiKey->forceFill(['revoked_at' => now()])->save();

    $response = callSigned('POST', '/api/v1/invoices', $this->payload, $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(401)->assertJsonPath('error.code', 'api_key_revoked');
});

it('rejects an expired api key', function () {
    $this->apiKey->forceFill(['expires_at' => now()->subDay()])->save();

    $response = callSigned('POST', '/api/v1/invoices', $this->payload, $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(401)->assertJsonPath('error.code', 'api_key_revoked');
});

it('rejects requests for an inactive project', function () {
    $this->project->forceFill(['is_active' => false])->save();

    $response = callSigned('POST', '/api/v1/invoices', $this->payload, $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(403)->assertJsonPath('error.code', 'project_inactive');
});

it('safely handles a replayed request within the valid timestamp window via idempotency', function () {
    $timestamp = now()->getTimestamp();

    $first = callSigned('POST', '/api/v1/invoices', $this->payload, $this->apiKeyPlain, $this->signingSecret, timestamp: $timestamp);
    $second = callSigned('POST', '/api/v1/invoices', $this->payload, $this->apiKeyPlain, $this->signingSecret, timestamp: $timestamp);

    $first->assertStatus(201);
    $second->assertStatus(200);

    expect($first->json('invoice_id'))->toBe($second->json('invoice_id'))
        ->and(Invoice::where('project_id', $this->project->id)->count())->toBe(1);
});

it('accepts a correctly signed request', function () {
    $response = callSigned('POST', '/api/v1/invoices', $this->payload, $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(201);
});
