<?php

declare(strict_types=1);

namespace App\Filament\Seller\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\QrisMerchant;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class LatestPaidWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Uang masuk terakhir';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $shop = Filament::getTenant();

        return $table
            ->query(
                fn () => Invoice::query()
                    ->where('qris_merchant_id', $shop instanceof QrisMerchant ? $shop->getKey() : null)
                    ->where('status', InvoiceStatus::Paid->value)
                    ->latest('paid_at')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i'),
                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->placeholder('-'),
                TextColumn::make('payment_amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.'))
                    ->weight('bold'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada uang masuk')
            ->emptyStateDescription('Pembayaran yang sudah Anda konfirmasi akan muncul di sini.');
    }
}
