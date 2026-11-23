<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Payments\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Seller\Resources\Payments\PaymentResource;
use App\Filament\Seller\Widgets\PaymentTotalsWidget;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListPayments extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'Pembayaran';

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $waiting = array_map(fn (InvoiceStatus $s): string => $s->value, PaymentResource::WAITING_STATUSES);

        return [
            'waiting' => Tab::make('Menunggu dicek')
                ->badge(PaymentResource::getEloquentQuery()->whereIn('status', $waiting)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $waiting)),
            'paid' => Tab::make('Sudah dibayar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Paid->value)),
            'expired' => Tab::make('Kedaluwarsa')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Expired->value)),
            'all' => Tab::make('Semua'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentTotalsWidget::class,
        ];
    }
}
