<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Support\ApiKeyGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        'is_active' => true,
    ]);

    $this->signingSecret = 'test-signing-secret-value';
    $generated = (new ApiKeyGenerator)->generate();
    $this->apiKeyPlain = $generated['key'];

    ApiKey::create([
        'project_id' => $this->project->id,
        'name' => 'Test key',
        'key_prefix' => $generated['prefix'],
        'key_hash' => $generated['hash'],
        'signing_secret' => $this->signingSecret,
    ]);
});

it('returns 429 with the standard error format once the per-key write limit is exceeded', function () {
    RateLimiter::for('api-write', fn () => Limit::perMinute(2)->by('test'));

    for ($i = 1; $i <= 2; $i++) {
        $response = callSigned('POST', '/api/v1/invoices', [
            'external_id' => "ORDER-{$i}",
            'amount' => 100000,
        ], $this->apiKeyPlain, $this->signingSecret);

        $response->assertStatus(201);
    }

    $limited = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-3',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret);

    $limited->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limit_exceeded')
        ->assertHeader('Retry-After');
});

it('keys the rate limit by api key, not by IP, so a different key is unaffected', function () {
    RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(1)->by(
        substr((string) $request->header('X-Api-Key'), 0, 24)
    ));

    callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret)->assertStatus(201);

    callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-2',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret)->assertStatus(429);

    $generated = (new ApiKeyGenerator)->generate();
    ApiKey::create([
        'project_id' => $this->project->id,
        'name' => 'Second key',
        'key_prefix' => $generated['prefix'],
        'key_hash' => $generated['hash'],
        'signing_secret' => 'another-secret',
    ]);

    callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-3',
        'amount' => 100000,
    ], $generated['key'], 'another-secret')->assertStatus(201);
});
