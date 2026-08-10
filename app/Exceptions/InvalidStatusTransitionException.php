<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use RuntimeException;

final class InvalidStatusTransitionException extends RuntimeException
{
    public static function forInvoice(Invoice $invoice, InvoiceStatus $target): self
    {
        return new self(
            "Invoice {$invoice->invoice_number} berstatus \"{$invoice->status->value}\" ".
            "tidak bisa diubah menjadi \"{$target->value}\"."
        );
    }
}
