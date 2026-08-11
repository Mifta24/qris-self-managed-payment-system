<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Antrian verifikasi pembayaran (Fase 4). Tidak ada create/edit — invoice
 * cuma dibuat lewat API dan status cuma berubah lewat
 * InvoicePaymentConfirmationService, tidak pernah lewat form admin.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    /**
     * Jumlah invoice yang callback-nya gagal total setelah 6 percobaan —
     * lihat SendCallbackJob::failed(). Ini penanda "butuh perhatian admin"
     * yang disebut di docs/api-contract.md.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereNotNull('callback_failed_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
