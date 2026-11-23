<?php

declare(strict_types=1);

namespace App\Filament\Seller\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\QrisMerchant;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ringkasan toko di beranda penjual. Semua query dibatasi ke toko (tenant)
 * yang sedang dibuka — widget tidak terkena scope tenancy otomatis seperti
 * resource, jadi pembatasannya ditulis eksplisit di sini.
 */
final class ShopOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Ringkasan toko';

    protected function getStats(): array
    {
        $paidToday = $this->paidQuery()->whereDate('paid_at', now()->toDateString());
        $paidThisMonth = $this->paidQuery()->where('paid_at', '>=', now()->startOfMonth());

        $waiting = $this->shopQuery()->whereIn('status', [
            InvoiceStatus::WaitingPayment->value,
            InvoiceStatus::ManualVerification->value,
        ])->count();

        return [
            Stat::make('Uang masuk hari ini', self::rupiah((int) (clone $paidToday)->sum('payment_amount')))
                ->description($paidToday->count().' pembayaran')
                ->color('success'),
            Stat::make('Menunggu dicek', (string) $waiting)
                ->description($waiting > 0 ? 'Cek GoPay Merchant, lalu konfirmasi' : 'Tidak ada yang menunggu')
                ->color($waiting > 0 ? 'warning' : 'success'),
            Stat::make('Uang masuk bulan ini', self::rupiah((int) (clone $paidThisMonth)->sum('payment_amount')))
                ->description($paidThisMonth->count().' pembayaran')
                ->color('success'),
        ];
    }

    /**
     * @return Builder<Invoice>
     */
    private function shopQuery(): Builder
    {
        $shop = Filament::getTenant();

        return Invoice::query()->where('qris_merchant_id', $shop instanceof QrisMerchant ? $shop->getKey() : null);
    }

    /**
     * @return Builder<Invoice>
     */
    private function paidQuery(): Builder
    {
        return $this->shopQuery()->where('status', InvoiceStatus::Paid->value);
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
