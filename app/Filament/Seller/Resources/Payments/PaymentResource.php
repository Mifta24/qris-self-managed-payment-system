<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Payments;

use App\Enums\InvoiceStatus;
use App\Filament\Seller\Resources\Payments\Pages\ListPayments;
use App\Filament\Seller\Resources\Payments\Tables\PaymentsTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Daftar pembayaran untuk penjual. Model-nya Invoice, tetapi tidak ada
 * create/edit/hapus: invoice dibuat lewat API dan statusnya hanya berubah
 * lewat InvoicePaymentConfirmationService. Dibatasi ke toko (tenant) yang
 * sedang dibuka lewat tenancy Filament (relasi `qrisMerchant`).
 */
final class PaymentResource extends Resource
{
    /**
     * Status yang berarti "pembayaran belum dikonfirmasi penjual".
     *
     * @var array<int, InvoiceStatus>
     */
    public const WAITING_STATUSES = [
        InvoiceStatus::WaitingPayment,
        InvoiceStatus::ManualVerification,
    ];

    protected static ?string $model = Invoice::class;

    protected static ?string $slug = 'pembayaran';

    protected static ?string $modelLabel = 'pembayaran';

    protected static ?string $pluralModelLabel = 'pembayaran';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()
            ->whereIn('status', array_map(fn (InvoiceStatus $s): string => $s->value, self::WAITING_STATUSES))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
