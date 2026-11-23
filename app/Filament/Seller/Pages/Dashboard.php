<?php

declare(strict_types=1);

namespace App\Filament\Seller\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

final class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Beranda';

    protected static ?string $navigationLabel = 'Beranda';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public function getSubheading(): string|Htmlable|null
    {
        return Filament::getTenant()?->label;
    }
}
