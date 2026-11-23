<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Seller\Pages\Auth\RegisterSeller;
use App\Filament\Seller\Pages\Auth\SellerLogin;
use App\Filament\Seller\Pages\Dashboard;
use App\Http\Middleware\UseIndonesianLocale;
use App\Models\QrisMerchant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel untuk penjual UMKM di /toko. Terpisah dari panel admin: bahasa
 * sehari-hari, hanya data toko milik akun yang login (tenancy per
 * QrisMerchant), dan tanpa menu teknis (API key, payload, callback).
 */
final class SellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('seller')
            ->path('toko')
            ->login(SellerLogin::class)
            ->registration(RegisterSeller::class)
            ->brandName('Kasir QRIS')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->tenant(QrisMerchant::class, slugAttribute: 'code')
            ->discoverResources(in: app_path('Filament/Seller/Resources'), for: 'App\Filament\Seller\Resources')
            ->discoverPages(in: app_path('Filament/Seller/Pages'), for: 'App\Filament\Seller\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Seller/Widgets'), for: 'App\Filament\Seller\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([
                UseIndonesianLocale::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
