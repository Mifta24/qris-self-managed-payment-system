<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\ApiKey;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Cuma tampilan ringkas, sengaja tanpa CreateAction/EditAction/DeleteAction.
 * Pembuatan API key men-generate key_hash + signing_secret di server
 * (lihat ListApiKeys::createApiKeyAction()) dan pencabutan lewat
 * ApiKeysTable::revokeAction() — kedua alur itu tidak boleh diduplikasi di
 * sini. Untuk kelola key, admin tetap buka resource ApiKey.
 */
class ApiKeysRelationManager extends RelationManager
{
    protected static string $relationship = 'apiKeys';

    protected static ?string $title = 'API Key';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('key_prefix')
                    ->label('API Key')
                    ->formatStateUsing(fn (string $state): string => "{$state}…")
                    ->weight(FontWeight::Medium)
                    ->copyable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(function (ApiKey $record): string {
                        if ($record->revoked_at !== null) {
                            return 'Dicabut';
                        }

                        if ($record->expires_at?->isPast()) {
                            return 'Kedaluwarsa';
                        }

                        return 'Aktif';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Kedaluwarsa' => 'warning',
                        'Dicabut' => 'danger',
                    }),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
