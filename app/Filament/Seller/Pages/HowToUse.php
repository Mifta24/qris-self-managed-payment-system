<?php

declare(strict_types=1);

namespace App\Filament\Seller\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Panduan singkat untuk penjual: cara mencocokkan mutasi GoPay Merchant
 * dengan daftar pembayaran, dalam bahasa sehari-hari.
 */
final class HowToUse extends Page
{
    protected static ?string $title = 'Cara Pakai';

    protected static ?string $navigationLabel = 'Cara Pakai';

    protected static ?string $slug = 'cara-pakai';

    protected static ?int $navigationSort = 90;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected string $view = 'filament.seller.pages.how-to-use';
}
