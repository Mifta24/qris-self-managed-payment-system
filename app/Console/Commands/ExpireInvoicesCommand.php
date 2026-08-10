<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceExpiryService;
use Illuminate\Console\Command;

/**
 * Dijadwalkan tiap menit lewat `routes/console.php`. Menangani invoice yang
 * kedaluwarsa tanpa pernah dibaca ulang lewat halaman pembayaran atau API
 * (jalur lazy di `InvoiceExpiryService` hanya jalan saat invoice dibaca).
 */
final class ExpireInvoicesCommand extends Command
{
    protected $signature = 'invoices:expire';

    protected $description = 'Pindahkan invoice waiting_payment yang sudah lewat expires_at menjadi expired';

    public function handle(InvoiceExpiryService $expiryService): int
    {
        $expiredCount = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::WaitingPayment)
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($invoices) use ($expiryService, &$expiredCount): void {
                foreach ($invoices as $invoice) {
                    if ($expiryService->expireIfDue($invoice)->status === InvoiceStatus::Expired) {
                        $expiredCount++;
                    }
                }
            });

        $this->info("{$expiredCount} invoice dipindahkan ke status expired.");

        return self::SUCCESS;
    }
}
