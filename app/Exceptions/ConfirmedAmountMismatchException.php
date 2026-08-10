<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

final class ConfirmedAmountMismatchException extends RuntimeException
{
    public static function forInvoice(Invoice $invoice, int $confirmedAmount): self
    {
        return new self(
            "Nominal yang diinput ({$confirmedAmount}) tidak cocok dengan payment_amount ".
            "invoice {$invoice->invoice_number} ({$invoice->payment_amount}). Konfirmasi ".
            'dibatalkan untuk mencegah salah pilih invoice.'
        );
    }
}
