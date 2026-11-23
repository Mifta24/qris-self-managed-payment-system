<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellerAccounts\Tables;

use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SellerAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (User $record): string => $record->qrisMerchants()->exists() ? 'Aktif' : 'Menunggu persetujuan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Aktif' ? 'success' : 'warning'),
                TextColumn::make('qrisMerchants.label')
                    ->label('Toko')
                    ->badge(),
                TextColumn::make('registration_note')
                    ->label('Catatan pendaftar')
                    ->limit(40)
                    ->tooltip(fn (User $record): ?string => $record->registration_note)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('pending')
                    ->label('Menunggu persetujuan')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('qrisMerchants')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
