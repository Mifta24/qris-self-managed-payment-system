<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Support\ApiKeyGenerator;
use Tests\Support\QrisTestPayload;

function createAuthenticatedProject(string $slug = 'restoran-a'): array
{
    $merchant = QrisMerchant::create([
        'code' => 'gopay-'.$slug,
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $project = Project::create([
        'slug' => $slug,
        'name' => 'Restoran '.$slug,
        'qris_merchant_id' => $merchant->id,
        'is_active' => true,
    ]);

    $signingSecret = 'signing-secret-'.$slug;
    $generated = (new ApiKeyGenerator)->generate();

    $apiKey = ApiKey::create([
        'project_id' => $project->id,
        'name' => 'Test key',
        'key_prefix' => $generated['prefix'],
        'key_hash' => $generated['hash'],
        'signing_secret' => $signingSecret,
    ]);

    return [$project, $apiKey, $generated['key'], $signingSecret];
}

beforeEach(function () {
    [$this->project, $this->apiKey, $this->apiKeyPlain, $this->signingSecret] = createAuthenticatedProject();
});

it('rejects invoice creation with missing required fields', function () {
    $response = callSigned('POST', '/api/v1/invoices', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.details.external_id.0', fn ($message) => is_string($message))
        ->assertJsonPath('error.details.amount.0', fn ($message) => is_string($message));
});

it('rejects a non positive amount', function () {
    $response = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 0,
    ], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
});

it('rejects an invalid customer_email', function () {
    $response = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 10000,
        'customer_email' => 'not-an-email',
    ], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
});

it('never returns the raw qris_payload in the create response', function () {
    $response = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(201)->assertJsonMissingPath('qris_payload');
});

it('returns invoice detail for the owning project', function () {
    $create = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret);

    $invoiceId = $create->json('invoice_id');

    $response = callSigned('GET', "/api/v1/invoices/{$invoiceId}", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200)
        ->assertJsonPath('invoice_id', $invoiceId)
        ->assertJsonPath('status', InvoiceStatus::WaitingPayment->value)
        ->assertJsonMissingPath('qris_payload');
});

it('returns a lightweight status payload for polling', function () {
    $create = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret);

    $invoiceId = $create->json('invoice_id');

    $response = callSigned('GET', "/api/v1/invoices/{$invoiceId}/status", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200)
        ->assertJsonPath('invoice_id', $invoiceId)
        ->assertJsonPath('status', InvoiceStatus::WaitingPayment->value);
});

it('returns 404 when a project tries to access another project\'s invoice', function () {
    $create = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret);

    $invoiceId = $create->json('invoice_id');

    [, , $otherApiKeyPlain, $otherSigningSecret] = createAuthenticatedProject('restoran-b');

    $response = callSigned('GET', "/api/v1/invoices/{$invoiceId}", [], $otherApiKeyPlain, $otherSigningSecret);

    $response->assertStatus(404)->assertJsonPath('error.code', 'invoice_not_found');
});

it('returns 404 for a nonexistent invoice number', function () {
    $response = callSigned('GET', '/api/v1/invoices/INV-DOES-NOT-EXIST', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(404)->assertJsonPath('error.code', 'invoice_not_found');
});

it('lazily expires a waiting_payment invoice whose expires_at has already passed on show', function () {
    $create = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret);

    $invoiceId = $create->json('invoice_id');
    Invoice::where('invoice_number', $invoiceId)->update(['expires_at' => now()->subMinute()]);

    $response = callSigned('GET', "/api/v1/invoices/{$invoiceId}", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200)->assertJsonPath('status', InvoiceStatus::Expired->value);
});

it('lazily expires a waiting_payment invoice whose expires_at has already passed on status', function () {
    $create = callSigned('POST', '/api/v1/invoices', [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ], $this->apiKeyPlain, $this->signingSecret);

    $invoiceId = $create->json('invoice_id');
    Invoice::where('invoice_number', $invoiceId)->update(['expires_at' => now()->subMinute()]);

    $response = callSigned('GET', "/api/v1/invoices/{$invoiceId}/status", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200)->assertJsonPath('status', InvoiceStatus::Expired->value);
});
