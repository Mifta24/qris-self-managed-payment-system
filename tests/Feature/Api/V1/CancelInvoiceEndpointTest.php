<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Jobs\SendCallbackJob;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    [$this->project, $this->apiKey, $this->apiKeyPlain, $this->signingSecret] = createAuthenticatedProject();
});

function createInvoiceViaApi(string $apiKeyPlain, string $signingSecret, string $externalId = 'ORDER-1'): string
{
    $response = callSigned('POST', '/api/v1/invoices', [
        'external_id' => $externalId,
        'amount' => 100000,
    ], $apiKeyPlain, $signingSecret);

    return $response->json('invoice_id');
}

it('cancels a waiting_payment invoice', function () {
    Queue::fake();

    $invoiceId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);

    $response = callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(200)
        ->assertJsonPath('invoice_id', $invoiceId)
        ->assertJsonPath('status', InvoiceStatus::Cancelled->value)
        ->assertJsonMissingPath('qris_payload');

    $invoice = Invoice::where('invoice_number', $invoiceId)->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Cancelled);
});

it('writes an audit log attributing the cancellation to the api key', function () {
    Queue::fake();

    $invoiceId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);
    $invoice = Invoice::where('invoice_number', $invoiceId)->firstOrFail();

    callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $this->apiKeyPlain, $this->signingSecret);

    $apiKey = ApiKey::where('key_prefix', substr($this->apiKeyPlain, 0, 24))->firstOrFail();

    $log = AuditLog::where('auditable_type', Invoice::class)
        ->where('auditable_id', $invoice->id)
        ->where('event', 'invoice.cancelled')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->api_key_id)->toBe($apiKey->id)
        ->and($log->user_id)->toBeNull()
        ->and($log->old_values)->toBe(['status' => InvoiceStatus::WaitingPayment->value])
        ->and($log->new_values)->toBe(['status' => InvoiceStatus::Cancelled->value]);
});

it('dispatches an invoice.cancelled callback job', function () {
    Queue::fake();

    $invoiceId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);
    $invoice = Invoice::where('invoice_number', $invoiceId)->firstOrFail();

    callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $this->apiKeyPlain, $this->signingSecret);

    Queue::assertPushed(SendCallbackJob::class, fn (SendCallbackJob $job): bool => $job->invoiceId === $invoice->id && $job->eventType === 'invoice.cancelled'
    );
});

it('rejects cancelling an already cancelled invoice', function () {
    Queue::fake();

    $invoiceId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);

    callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $this->apiKeyPlain, $this->signingSecret);
    $response = callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(409)->assertJsonPath('error.code', 'invoice_not_cancellable');
});

it('rejects cancelling a paid invoice', function () {
    Queue::fake();

    $invoiceId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);
    Invoice::where('invoice_number', $invoiceId)->update(['status' => InvoiceStatus::Paid]);

    $response = callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(409)->assertJsonPath('error.code', 'invoice_not_cancellable');
});

it('lazily expires a due invoice before rejecting its cancellation', function () {
    Queue::fake();

    $invoiceId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);
    Invoice::where('invoice_number', $invoiceId)->update(['expires_at' => now()->subMinute()]);

    $response = callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(409)->assertJsonPath('error.code', 'invoice_not_cancellable');

    $invoice = Invoice::where('invoice_number', $invoiceId)->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Expired);
});

it('returns 404 when a project tries to cancel another project\'s invoice', function () {
    Queue::fake();

    $invoiceId = createInvoiceViaApi($this->apiKeyPlain, $this->signingSecret);

    [, , $otherApiKeyPlain, $otherSigningSecret] = createAuthenticatedProject('restoran-b');

    $response = callSigned('POST', "/api/v1/invoices/{$invoiceId}/cancel", [], $otherApiKeyPlain, $otherSigningSecret);

    $response->assertStatus(404)->assertJsonPath('error.code', 'invoice_not_found');

    $invoice = Invoice::where('invoice_number', $invoiceId)->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::WaitingPayment);
});

it('returns 404 for a nonexistent invoice number', function () {
    $response = callSigned('POST', '/api/v1/invoices/INV-DOES-NOT-EXIST/cancel', [], $this->apiKeyPlain, $this->signingSecret);

    $response->assertStatus(404)->assertJsonPath('error.code', 'invoice_not_found');
});
