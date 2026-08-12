<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Cuma tampilan ringkas, sengaja tanpa CreateAction/AssociateAction. Project
 * baru dibuat lewat ProjectResource (butuh unique_code_min/max, invoice_ttl,
 * dll — bukan cuma name) dan pemilihan qris_merchant-nya adalah bagian dari
 * form itu, bukan sesuatu yang aman diubah lewat associate/dissociate di
 * sini.
 */
class ProjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'projects';

    protected static ?string $title = 'Project';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('unique_code_min')
                    ->label('Kode unik')
                    ->formatStateUsing(fn (mixed $state, $record): string => "{$record->unique_code_min}-{$record->unique_code_max}"),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
