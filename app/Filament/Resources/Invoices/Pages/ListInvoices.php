<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Tidak ada action "buat baru" — invoice cuma dibuat lewat API (lihat
 * InvoiceController::store()), tidak pernah manual dari dashboard.
 */
class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;
}
