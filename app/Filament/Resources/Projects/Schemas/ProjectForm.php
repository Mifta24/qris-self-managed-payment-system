<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\QrisMerchant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * `notification_config` sengaja tidak ditampilkan — kolomnya ada di skema
 * tapi belum dipakai (notifikasi Telegram Fase 4 pakai satu chat admin
 * global, bukan per-project). Jangan tampilkan field yang tidak berfungsi.
 */
class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated()
                    ->helperText('Dipakai sebagai project_id di kontrak API. Tidak bisa diubah setelah dibuat.'),
                TextInput::make('name')
                    ->required(),
                Select::make('qris_merchant_id')
                    ->label('QRIS Merchant')
                    ->options(fn (): array => QrisMerchant::query()->pluck('label', 'id')->all())
                    ->required(),
                TextInput::make('callback_url')
                    ->url(),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
                TextInput::make('invoice_ttl_minutes')
                    ->label('TTL invoice (menit)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(30),
                TextInput::make('unique_code_min')
                    ->label('Kode unik minimum')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(1),
                TextInput::make('unique_code_max')
                    ->label('Kode unik maksimum')
                    ->required()
                    ->numeric()
                    ->gte('unique_code_min')
                    ->default(999),
            ]);
    }
}
