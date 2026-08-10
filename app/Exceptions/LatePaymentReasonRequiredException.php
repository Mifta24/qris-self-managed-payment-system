<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

final class LatePaymentReasonRequiredException extends RuntimeException
{
    public static function forInvoice(Invoice $invoice): self
    {
        return new self(
            "Invoice {$invoice->invoice_number} sudah kedaluwarsa. Catatan alasan wajib diisi ".
            'untuk mengonfirmasi pembayaran terlambat.'
        );
    }
}
