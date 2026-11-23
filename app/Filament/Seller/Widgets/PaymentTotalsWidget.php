<?php

declare(strict_types=1);

namespace App\Filament\Seller\Widgets;

use App\Filament\Seller\Resources\Payments\Pages\ListPayments;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Jumlah dan total pembayaran sesuai tab dan filter yang sedang aktif di
 * halaman Pembayaran. Hanya dipasang di halaman itu, bukan di beranda.
 */
final class PaymentTotalsWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static bool $isDiscovered = false;

    protected function getTablePage(): string
    {
        return ListPayments::class;
    }

    protected function getStats(): array
    {
        // reorder(): urutan tabel tidak boleh ikut ke query agregat (PostgreSQL menolak
        // ORDER BY kolom non-agregat pada SELECT SUM tanpa GROUP BY).
        $query = $this->getPageTableQuery()->reorder();

        $count = (clone $query)->count();
        $total = (int) (clone $query)->sum('payment_amount');

        $label = match ($this->activeTab) {
            'paid' => 'Total uang masuk',
            'waiting' => 'Total yang ditunggu',
            default => 'Total jumlah',
        };

        return [
            Stat::make('Jumlah pembayaran', (string) $count),
            Stat::make($label, 'Rp '.number_format($total, 0, ',', '.'))
                ->color($this->activeTab === 'paid' ? 'success' : 'gray'),
        ];
    }
}
