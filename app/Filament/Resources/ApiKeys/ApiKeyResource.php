<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeys;

use App\Filament\Resources\ApiKeys\Pages\EditApiKey;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Filament\Resources\ApiKeys\Schemas\ApiKeyForm;
use App\Filament\Resources\ApiKeys\Tables\ApiKeysTable;
use App\Models\ApiKey;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Tidak ada halaman create standar — dua secret (API key, signing_secret)
 * cuma boleh digenerate server-side lewat action "Buat API Key" di
 * ApiKeysTable, tidak pernah diketik admin. Lihat ApiKeyForm untuk kenapa
 * form edit-nya juga tidak menyentuh kolom secret sama sekali.
 */
class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ApiKeyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiKeysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiKeys::route('/'),
            'edit' => EditApiKey::route('/{record}/edit'),
        ];
    }
}
