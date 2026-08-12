<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan yang langsung terlihat begitu admin login, tanpa harus buka
 * tab Invoices dulu. Lihat docs/roadmap.md Fase 5.
 */
class InvoiceStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $waitingVerification = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::WaitingPayment->value,
                InvoiceStatus::ManualVerification->value,
            ])
            ->count();

        $paidToday = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereDate('paid_at', now())
            ->get(['payment_amount']);

        $callbackFailed = Invoice::query()
            ->whereNotNull('callback_failed_at')
            ->count();

        return [
            Stat::make('Menunggu verifikasi', (string) $waitingVerification)
                ->description('Invoice menunggu dicek admin')
                ->color($waitingVerification > 0 ? 'warning' : 'success'),
            Stat::make('Dibayar hari ini', (string) $paidToday->count())
                ->description('Rp '.number_format((int) $paidToday->sum('payment_amount'), 0, ',', '.'))
                ->color('success'),
            Stat::make('Callback gagal total', (string) $callbackFailed)
                ->description('Butuh perhatian admin')
                ->color($callbackFailed > 0 ? 'danger' : 'success'),
        ];
    }
}
