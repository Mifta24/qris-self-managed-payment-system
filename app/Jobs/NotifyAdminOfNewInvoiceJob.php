<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi invoice baru dibuat — BUKAN notifikasi pembayaran berhasil.
 * Lihat catatan di docs/architecture.md: jangan pernah menulis teks yang
 * bisa disalahartikan sebagai konfirmasi. Hanya memakai field turunan
 * (invoice_number, project, payment_amount, expires_at) — qris_payload
 * tidak pernah disentuh di sini.
 */
final class NotifyAdminOfNewInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (blank($botToken) || blank($chatId)) {
            Log::warning('Notifikasi Telegram dilewati: TELEGRAM_BOT_TOKEN/TELEGRAM_ADMIN_CHAT_ID belum diisi.');

            return;
        }

        Http::asForm()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $this->message(),
            ])
            ->throw();
    }

    private function message(): string
    {
        $amount = number_format($this->invoice->payment_amount, 0, ',', '.');
        $expiresAt = $this->invoice->expires_at?->translatedFormat('d M Y H:i').' WIB';

        return <<<TEXT
        Invoice baru dibuat
        Invoice: {$this->invoice->invoice_number}
        Project: {$this->invoice->project->name}
        Nominal: Rp {$amount}
        Kedaluwarsa: {$expiresAt}

        Ini bukan konfirmasi pembayaran. Cocokkan dengan mutasi masuk di dashboard sebelum mengonfirmasi.
        TEXT;
    }
}
