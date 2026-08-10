<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QrisMerchantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('label')
                    ->required(),
                Textarea::make('static_payload')
                    ->label('Payload QRIS statis')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->afterStateHydrated(fn ($component) => $component->state(null))
                    ->helperText('Kosongkan untuk mempertahankan payload yang sudah ada. Isi hanya untuk menggantinya. Nilai yang tersimpan tidak pernah ditampilkan balik ke sini.')
                    ->columnSpanFull(),
                TextInput::make('merchant_name'),
                TextInput::make('merchant_city'),
                TextInput::make('national_merchant_id'),
                TextInput::make('currency')
                    ->required()
                    ->default('360'),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
                DateTimePicker::make('verified_at')
                    ->label('Terakhir diverifikasi')
                    ->helperText('Kapan payload ini terakhir diuji scan sungguhan.'),
            ]);
    }
}
