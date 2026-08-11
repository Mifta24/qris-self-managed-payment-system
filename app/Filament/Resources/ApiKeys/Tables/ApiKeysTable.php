<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeys\Tables;

use App\Models\ApiKey;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiKeysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->searchable(),
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
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'kedaluwarsa' => 'Kedaluwarsa',
                        'dicabut' => 'Dicabut',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'dicabut' => $query->whereNotNull('revoked_at'),
                            'kedaluwarsa' => $query->whereNull('revoked_at')->where('expires_at', '<', now()),
                            'aktif' => $query->whereNull('revoked_at')
                                ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now())),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                self::revokeAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label('Cabut')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (ApiKey $record): bool => $record->revoked_at === null)
            ->action(function (ApiKey $record): void {
                $record->forceFill(['revoked_at' => now()])->save();

                Notification::make()
                    ->title('API Key dicabut')
                    ->success()
                    ->send();
            });
    }
}
