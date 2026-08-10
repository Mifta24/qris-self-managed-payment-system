<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * Read-only. Invoice tidak pernah diedit lewat form admin — transisi status
 * cuma lewat InvoicePaymentConfirmationService (lihat action konfirmasi di
 * InvoicesTable). qris_payload sengaja tidak pernah ditampilkan di sini,
 * sesuai aturan mutlak #5 di CLAUDE.md.
 */
class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('invoice_number'),
                TextEntry::make('project.name')->label('Project'),
                TextEntry::make('external_id'),
                TextEntry::make('status')->badge(),
                TextEntry::make('description'),
                TextEntry::make('customer_name'),
                TextEntry::make('customer_email'),
                TextEntry::make('customer_phone'),
                TextEntry::make('base_amount')
                    ->label('Nominal dasar')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.')),
                TextEntry::make('unique_code')->label('Kode unik'),
                TextEntry::make('payment_amount')
                    ->label('Total pembayaran')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.')),
                TextEntry::make('expires_at')->dateTime(),
                TextEntry::make('paid_at')->dateTime(),
                TextEntry::make('confirmedBy.name')->label('Dikonfirmasi oleh'),
                TextEntry::make('confirmed_at')->dateTime(),
                TextEntry::make('confirmation_reference')->label('RRN'),
                TextEntry::make('confirmation_note')->label('Catatan konfirmasi'),
                TextEntry::make('cancelled_at')->dateTime(),
                TextEntry::make('cancellation_reason'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
