<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Jobs\SendCallbackJob;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembatalan invoice lewat API oleh project pemiliknya. Terpisah dari
 * InvoicePaymentConfirmationService karena aktornya berbeda (ApiKey, bukan
 * User admin) dan tidak melewati verifikasi nominal. Lihat alur di
 * docs/architecture.md.
 */
final class InvoiceCancellationService
{
    /**
     * @throws InvalidStatusTransitionException status invoice tidak boleh menjadi cancelled.
     */
    public function cancel(Invoice $invoice, ApiKey $apiKey): Invoice
    {
        $invoice = DB::transaction(function () use ($invoice, $apiKey): Invoice {
            // Kunci baris ini dulu, baru validasi ulang state-nya setelah lock
            // didapat — sama seperti InvoicePaymentConfirmationService, supaya
            // dua request cancel/confirm hampir bersamaan tidak lolos berdua.
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->status->canTransitionTo(InvoiceStatus::Cancelled)) {
                throw InvalidStatusTransitionException::forInvoice($invoice, InvoiceStatus::Cancelled);
            }

            $oldValues = ['status' => $invoice->status->value];

            $invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();

            AuditLog::create([
                'auditable_type' => Invoice::class,
                'auditable_id' => $invoice->id,
                'event' => 'invoice.cancelled',
                'old_values' => $oldValues,
                'new_values' => ['status' => InvoiceStatus::Cancelled->value],
                'api_key_id' => $apiKey->id,
            ]);

            return $invoice;
        });

        // Di luar transaksi, sama seperti service lain: koneksi queue
        // `database` tidak afterCommit secara default.
        SendCallbackJob::dispatch($invoice->id, 'invoice.cancelled', (string) Str::uuid());

        return $invoice;
    }
}
