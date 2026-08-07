<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * State machine status invoice. Lihat tabel transisi di docs/architecture.md.
 *
 * Transisi `expired -> paid` sengaja diizinkan di sini untuk kasus pembayaran
 * terlambat, tapi pengecekan tambahan (nominal belum dipakai invoice aktif
 * lain, catatan alasan wajib diisi) adalah tanggung jawab service, bukan
 * enum ini.
 */
enum InvoiceStatus: string
{
    case Created = 'created';
    case WaitingPayment = 'waiting_payment';
    case ManualVerification = 'manual_verification';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * @return array<int, self>
     */
    private function allowedTargets(): array
    {
        return match ($this) {
            self::Created => [self::WaitingPayment, self::Failed],
            self::WaitingPayment => [self::ManualVerification, self::Paid, self::Expired, self::Cancelled],
            self::ManualVerification => [self::Paid, self::WaitingPayment],
            self::Expired => [self::Paid],
            self::Paid, self::Cancelled, self::Failed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), strict: true);
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Cancelled, self::Failed => true,
            default => false,
        };
    }

    /**
     * Cocok dengan status yang termasuk di kondisi WHERE index
     * `invoices_active_payment_amount_unique`. Lihat docs/database.md.
     */
    public function locksAmount(): bool
    {
        return match ($this) {
            self::Created, self::WaitingPayment, self::ManualVerification => true,
            default => false,
        };
    }
}
