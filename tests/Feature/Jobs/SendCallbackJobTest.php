<?php

declare(strict_types=1);

use App\Jobs\SendCallbackJob;
use App\Models\AuditLog;
use App\Models\CallbackLog;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\QrisTestPayload;

const CALLBACK_URL = 'https://project.example.com/callback';
const CALLBACK_SECRET = 'test-callback-signing-secret';

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
        'callback_signing_secret' => CALLBACK_SECRET,
    ]);

    $this->invoice = app(InvoiceService::class)->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
        'callback_url' => CALLBACK_URL,
    ]);
});

function attemptJob(int $attempt, string $eventType = 'invoice.paid', ?string $eventId = null): SendCallbackJob
{
    $job = new SendCallbackJob(test()->invoice->id, $eventType, $eventId ?? (string) Str::uuid());
    $job->withFakeQueueInteractions();
    $job->job->attempts = $attempt;

    return $job;
}

it('sends a signed POST request and logs a succeeded callback_logs row', function () {
    Http::fake([CALLBACK_URL => Http::response(['ok' => true], 200)]);

    $eventId = (string) Str::uuid();
    attemptJob(1, 'invoice.paid', $eventId)->handle();

    Http::assertSent(function ($request) use ($eventId) {
        $timestamp = $request->header('X-Qris-Timestamp')[0];
        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$request->body()}", CALLBACK_SECRET);

        return $request->url() === CALLBACK_URL
            && $request->hasHeader('X-Qris-Event', 'invoice.paid')
            && $request->hasHeader('X-Qris-Event-Id', $eventId)
            && $request->hasHeader('X-Qris-Signature', $expectedSignature);
    });

    $log = CallbackLog::sole();

    expect($log->invoice_id)->toBe($this->invoice->id)
        ->and($log->event_id)->toBe($eventId)
        ->and($log->event_type)->toBe('invoice.paid')
        ->and($log->attempt)->toBe(1)
        ->and($log->response_status)->toBe(200)
        ->and($log->succeeded_at)->not->toBeNull()
        ->and($log->next_retry_at)->toBeNull()
        ->and($log->request_payload['invoice_id'])->toBe($this->invoice->invoice_number);
});

it('logs a failed attempt, schedules the next retry, and rethrows on a non-2xx response', function () {
    Http::fake([CALLBACK_URL => Http::response('server error', 500)]);

    $before = now();

    expect(fn () => attemptJob(1)->handle())->toThrow(RequestException::class);

    $log = CallbackLog::sole();

    expect($log->attempt)->toBe(1)
        ->and($log->response_status)->toBe(500)
        ->and($log->succeeded_at)->toBeNull()
        ->and($log->next_retry_at)->not->toBeNull()
        ->and($log->next_retry_at->getTimestamp() - $before->getTimestamp())->toBeGreaterThanOrEqual(9)
        ->and($log->next_retry_at->getTimestamp() - $before->getTimestamp())->toBeLessThanOrEqual(11);
});

it('computes next_retry_at from the correct backoff slot for a later attempt', function () {
    Http::fake([CALLBACK_URL => Http::response('server error', 500)]);

    $before = now();

    // Percobaan ke-5 gagal -> jeda sebelum percobaan ke-6 adalah slot backoff
    // terakhir (2 jam / 7200 detik).
    expect(fn () => attemptJob(5)->handle())->toThrow(RequestException::class);

    $log = CallbackLog::sole();

    expect($log->next_retry_at->getTimestamp() - $before->getTimestamp())->toBeGreaterThanOrEqual(7199)
        ->and($log->next_retry_at->getTimestamp() - $before->getTimestamp())->toBeLessThanOrEqual(7201);
});

it('does not schedule a retry on the final attempt', function () {
    Http::fake([CALLBACK_URL => Http::response('server error', 500)]);

    expect(fn () => attemptJob(6)->handle())->toThrow(RequestException::class);

    $log = CallbackLog::sole();

    expect($log->next_retry_at)->toBeNull();
});

it('logs the connection error message instead of a response status on a timeout', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    expect(fn () => attemptJob(1)->handle())->toThrow(ConnectionException::class);

    $log = CallbackLog::sole();

    expect($log->response_status)->toBeNull()
        ->and($log->error_message)->toBe('Connection timed out')
        ->and($log->succeeded_at)->toBeNull();
});

it('does nothing when the invoice has no callback_url', function () {
    $this->invoice->forceFill(['callback_url' => null])->save();

    Http::fake();

    attemptJob(1)->handle();

    Http::assertNothingSent();
    expect(CallbackLog::count())->toBe(0);
});

it('marks the invoice as needing attention and writes an audit log once all attempts are exhausted', function () {
    $job = attemptJob(6, 'invoice.paid', $eventId = (string) Str::uuid());

    $job->failed(new RuntimeException('exhausted'));

    expect($this->invoice->fresh()->callback_failed_at)->not->toBeNull();

    $log = AuditLog::where('auditable_id', $this->invoice->id)->where('event', 'invoice.callback_failed')->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->toBe(['event_type' => 'invoice.paid', 'event_id' => $eventId]);
});
