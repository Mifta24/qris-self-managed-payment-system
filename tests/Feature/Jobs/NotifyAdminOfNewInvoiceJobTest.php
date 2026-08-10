<?php

declare(strict_types=1);

use App\Jobs\NotifyAdminOfNewInvoiceJob;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
});

it('sends a telegram message with the invoice details', function () {
    config(['services.telegram.bot_token' => 'test-token', 'services.telegram.admin_chat_id' => '12345']);
    Http::fake();
    Queue::fake();

    $invoice = app(InvoiceService::class)->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    (new NotifyAdminOfNewInvoiceJob($invoice))->handle();

    Http::assertSent(function ($request) use ($invoice) {
        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $request['chat_id'] === '12345'
            && str_contains($request['text'], $invoice->invoice_number)
            && str_contains($request['text'], 'bukan konfirmasi pembayaran');
    });
});

it('never mentions the raw qris_payload or confirmation-sounding words', function () {
    config(['services.telegram.bot_token' => 'test-token', 'services.telegram.admin_chat_id' => '12345']);
    Http::fake();
    Queue::fake();

    $invoice = app(InvoiceService::class)->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    (new NotifyAdminOfNewInvoiceJob($invoice))->handle();

    Http::assertSent(function ($request) use ($invoice) {
        $text = $request['text'];

        return ! str_contains($text, $invoice->qris_payload)
            && ! str_contains($text, 'lunas')
            && ! str_contains($text, 'berhasil dibayar');
    });
});

it('logs a warning and does not call the http client when telegram config is missing', function () {
    config(['services.telegram.bot_token' => null, 'services.telegram.admin_chat_id' => null]);
    Http::fake();
    Queue::fake();

    $invoice = app(InvoiceService::class)->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    (new NotifyAdminOfNewInvoiceJob($invoice))->handle();

    Http::assertNothingSent();
});
