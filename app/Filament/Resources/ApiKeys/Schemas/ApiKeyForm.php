<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeys\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Ini form EDIT, bukan create — key_hash/signing_secret sengaja tidak ada
 * di sini sama sekali, admin tidak boleh mengetiknya. Pembuatan API key
 * baru lewat action "Buat API Key" di ApiKeysTable yang men-generate
 * keduanya di server. Lihat $hidden di App\Models\ApiKey yang membuat
 * kolom-kolom itu otomatis tidak pernah terisi balik ke form ini.
 */
class ApiKeyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('key_prefix')
                    ->label('API Key (prefix)')
                    ->content(fn ($record): string => $record->key_prefix.'…'),
                TextInput::make('name')
                    ->required(),
                TagsInput::make('allowed_ips')
                    ->label('IP yang diizinkan')
                    ->helperText('Kosongkan untuk mengizinkan semua IP.'),
                DateTimePicker::make('expires_at'),
            ]);
    }
}
