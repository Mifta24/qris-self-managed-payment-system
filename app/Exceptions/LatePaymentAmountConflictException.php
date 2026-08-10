<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

final class LatePaymentAmountConflictException extends RuntimeException
{
    public static function forInvoice(Invoice $invoice): self
    {
        $formattedAmount = number_format($invoice->payment_amount, 0, ',', '.');

        return new self(
            "Nominal invoice {$invoice->invoice_number} (Rp {$formattedAmount}) sudah dipakai ".
            'invoice aktif lain sejak invoice ini kedaluwarsa. Selesaikan konflik ini secara '.
            'manual sebelum mengonfirmasi pembayaran terlambat.'
        );
    }
}
