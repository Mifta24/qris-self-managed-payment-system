<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Transisi `waiting_payment -> expired` saat `expires_at` sudah lewat.
 * Dipanggil dari dua jalur: scheduler (`ExpireInvoicesCommand`) dan lazy saat
 * invoice dibaca (halaman pembayaran publik + API). Keduanya lewat method
 * yang sama supaya audit log dan penanganan race condition tidak
 * terduplikasi.
 */
final class InvoiceExpiryService
{
    public function expireIfDue(Invoice $invoice): Invoice
    {
        if (! $this->isDue($invoice)) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $this->isDue($locked)) {
                return $locked;
            }

            $oldValues = ['status' => $locked->status->value];

            $locked->forceFill(['status' => InvoiceStatus::Expired])->save();

            AuditLog::create([
                'auditable_type' => Invoice::class,
                'auditable_id' => $locked->id,
                'event' => 'invoice.expired',
                'old_values' => $oldValues,
                'new_values' => ['status' => InvoiceStatus::Expired->value],
            ]);

            return $locked;
        });
    }

    private function isDue(Invoice $invoice): bool
    {
        return $invoice->status->canTransitionTo(InvoiceStatus::Expired)
            && $invoice->expires_at !== null
            && $invoice->expires_at->isPast();
    }
}
