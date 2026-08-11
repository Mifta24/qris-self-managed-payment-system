<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Resources\InvoiceResource;
use App\Models\AuditLog;
use App\Models\CallbackLog;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kirim callback ke project saat invoice berubah jadi paid/expired/cancelled.
 * Lihat docs/api-contract.md untuk kontrak header, signature, dan aturan
 * retry. `eventId` dibuat sekali oleh pemanggil (lihat InvoicePaymentConfirmationService,
 * InvoiceExpiryService) dan tetap sama di semua percobaan retry supaya
 * project bisa idempotent.
 */
final class SendCallbackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(
        public readonly int $invoiceId,
        public readonly string $eventType,
        public readonly string $eventId,
    ) {}

    /**
     * Jeda sebelum percobaan ke-2 sampai ke-6. Setelah percobaan ke-6 gagal,
     * failed() menandai invoice butuh perhatian admin — lihat docs/api-contract.md.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 1800, 7200];
    }

    public function handle(): void
    {
        $invoice = Invoice::with('project')->findOrFail($this->invoiceId);

        if (blank($invoice->callback_url)) {
            return;
        }

        $payload = (new InvoiceResource($invoice))->resolve();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $invoice->project->callback_signing_secret);

        $startedAt = microtime(true);

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'X-Qris-Event' => $this->eventType,
                    'X-Qris-Event-Id' => $this->eventId,
                    'X-Qris-Timestamp' => $timestamp,
                    'X-Qris-Signature' => $signature,
                ])
                ->timeout(10)
                ->post($invoice->callback_url);

            $this->log($invoice, $payload, $signature, $startedAt, response: $response);

            $response->throw();
        } catch (RequestException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            $this->log($invoice, $payload, $signature, $startedAt, errorMessage: $e->getMessage());

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Invoice::whereKey($this->invoiceId)->update(['callback_failed_at' => now()]);

        AuditLog::create([
            'auditable_type' => Invoice::class,
            'auditable_id' => $this->invoiceId,
            'event' => 'invoice.callback_failed',
            'old_values' => null,
            'new_values' => ['event_type' => $this->eventType, 'event_id' => $this->eventId],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function log(
        Invoice $invoice,
        array $payload,
        string $signature,
        float $startedAt,
        ?Response $response = null,
        ?string $errorMessage = null,
    ): void {
        $attempt = $this->attempts();
        $succeeded = $response?->successful() ?? false;
        $hasMoreAttempts = $attempt < $this->tries;

        CallbackLog::create([
            'invoice_id' => $invoice->id,
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'url' => $invoice->callback_url,
            'attempt' => $attempt,
            'request_payload' => $payload,
            'request_signature' => $signature,
            'response_status' => $response?->status(),
            'response_body' => $response !== null ? Str::limit($response->body(), 2000) : null,
            'error_message' => $errorMessage !== null ? Str::limit($errorMessage, 255) : null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'succeeded_at' => $succeeded ? now() : null,
            'next_retry_at' => (! $succeeded && $hasMoreAttempts) ? now()->addSeconds($this->backoff()[$attempt - 1]) : null,
        ]);
    }
}
