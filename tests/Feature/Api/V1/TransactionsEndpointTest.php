<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

beforeEach(function () {
    [$this->project, $this->apiKey, $this->apiKeyPlain, $this->signingSecret] = createAuthenticatedProject();
});

it('lists only invoices belonging to the authenticated project', function () {
    createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret, 'ORDER-1');
    createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret, 'ORDER-2');

    [, , $otherApiKeyPlain, $otherSigningSecret] = createAuthenticatedProject('restoran-b');
    createInvoiceViaApi($otherApiKeyPlain, $otherSigningSecret, 'ORDER-1');

    $response = callSigned('GET', '/api/v1/transactions', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('external_id')->sort()->values()->all())
        ->toBe(['ORDER-1', 'ORDER-2']);
});

it('never includes the raw qris_payload in the list', function () {
    createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);

    $response = callSigned('GET', '/api/v1/transactions', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200)->assertJsonMissingPath('data.0.qris_payload');
});

it('filters by status', function () {
    $paidId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret, 'ORDER-PAID');
    createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret, 'ORDER-WAITING');
    Invoice::where('invoice_number', $paidId)->update(['status' => InvoiceStatus::Paid]);

    $response = callSigned('GET', '/api/v1/transactions?status=paid', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.invoice_id'))->toBe($paidId);
});

it('rejects an invalid status filter', function () {
    $response = callSigned('GET', '/api/v1/transactions?status=not_a_real_status', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
});

it('paginates results honoring per_page', function () {
    foreach (range(1, 3) as $i) {
        createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret, "ORDER-{$i}");
    }

    $response = callSigned('GET', '/api/v1/transactions?per_page=2', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.total'))->toBe(3);
    expect($response->json('meta.per_page'))->toBe(2);
});

it('rejects a per_page above the allowed maximum', function () {
    $response = callSigned('GET', '/api/v1/transactions?per_page=500', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
});
