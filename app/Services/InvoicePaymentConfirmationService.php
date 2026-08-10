<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\ConfirmedAmountMismatchException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\LatePaymentAmountConflictException;
use App\Exceptions\LatePaymentReasonRequiredException;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Konfirmasi pembayaran oleh admin. Terpisah dari InvoiceService karena ini
 * aksi lifecycle berbeda dengan aktor berbeda (User admin, bukan Project/API
 * key) — lihat alur di docs/architecture.md.
 */
final class InvoicePaymentConfirmationService
{
    /**
     * @param  array{
     *     confirmed_amount: int,
     *     confirmation_reference: string,
     *     paid_at: Carbon,
     *     confirmation_note?: ?string,
     * }  $data
     *
     * @throws ConfirmedAmountMismatchException nominal yang diinput admin tidak cocok.
     * @throws InvalidStatusTransitionException status invoice tidak boleh menjadi paid.
     * @throws LatePaymentReasonRequiredException pembayaran terlambat tanpa catatan alasan.
     * @throws LatePaymentAmountConflictException nominal sudah dipakai invoice aktif lain.
     */
    public function confirm(Invoice $invoice, User $admin, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $admin, $data): Invoice {
            // Kunci baris ini dulu, baru validasi ulang state-nya setelah lock
            // didapat — supaya dua admin yang menekan konfirmasi hampir
            // bersamaan pada invoice yang sama tidak lolos berdua.
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($data['confirmed_amount'] !== $invoice->payment_amount) {
                throw ConfirmedAmountMismatchException::forInvoice($invoice, $data['confirmed_amount']);
            }

            if (! $invoice->status->canTransitionTo(InvoiceStatus::Paid)) {
                throw InvalidStatusTransitionException::forInvoice($invoice, InvoiceStatus::Paid);
            }

            $isLatePayment = $invoice->status === InvoiceStatus::Expired;

            if ($isLatePayment && blank($data['confirmation_note'] ?? null)) {
                throw LatePaymentReasonRequiredException::forInvoice($invoice);
            }

            if ($isLatePayment && $this->amountNowLockedByAnotherInvoice($invoice)) {
                throw LatePaymentAmountConflictException::forInvoice($invoice);
            }

            $oldValues = ['status' => $invoice->status->value];

            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $data['paid_at'],
                'confirmed_at' => now(),
                'confirmed_by' => $admin->id,
                'confirmation_reference' => $data['confirmation_reference'],
                'confirmation_note' => $data['confirmation_note'] ?? null,
            ])->save();

            AuditLog::create([
                'auditable_type' => Invoice::class,
                'auditable_id' => $invoice->id,
                'event' => $isLatePayment ? 'invoice.paid.late_payment' : 'invoice.paid',
                'old_values' => $oldValues,
                'new_values' => ['status' => InvoiceStatus::Paid->value, 'late_payment' => $isLatePayment],
                'user_id' => $admin->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Dicek dari status yang sama-sama dianggap "aktif" oleh
     * `invoices_active_payment_amount_unique` (lihat
     * `InvoiceStatus::locksAmount()`). Bukan constraint database — invoice
     * `paid` sengaja tidak masuk index itu — jadi ini pengecekan level
     * aplikasi, bukan jaminan mutlak terhadap race dua konfirmasi
     * bersamaan.
     */
    private function amountNowLockedByAnotherInvoice(Invoice $invoice): bool
    {
        $activeStatuses = array_map(
            fn (InvoiceStatus $status): string => $status->value,
            array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->locksAmount()),
        );

        return Invoice::where('qris_merchant_id', $invoice->qris_merchant_id)
            ->where('payment_amount', $invoice->payment_amount)
            ->where('id', '!=', $invoice->id)
            ->whereIn('status', $activeStatuses)
            ->exists();
    }
}
