<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants\Tables;

use App\Exceptions\NoAvailableAmountException;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * static_payload sengaja tidak pernah jadi kolom tabel — lihat aturan
 * mutlak #5 di CLAUDE.md dan $hidden di App\Models\QrisMerchant.
 */
class QrisMerchantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('merchant_name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('verified_at')
                    ->label('Verifikasi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? 'Belum diverifikasi'
                        : Carbon::parse($state)->format('d M Y H:i'))
                    ->color(fn (?string $state): string => $state === null ? 'warning' : 'success')
                    ->sortable(),
                TextColumn::make('projects_count')
                    ->label('Jumlah project')
                    ->counts('projects')
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Mata uang')
                    ->formatStateUsing(fn (string $state): string => $state === '360' ? 'IDR' : $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status aktif'),
                Filter::make('belum_diverifikasi')
                    ->label('Belum diverifikasi')
                    ->query(fn (Builder $query): Builder => $query->whereNull('verified_at')),
            ])
            ->recordActions([
                EditAction::make(),
                self::testQrisAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Bikin invoice sungguhan (lewat InvoiceService yang sama dengan API v1)
     * supaya admin bisa scan QR-nya untuk memastikan static_payload yang
     * baru diisi benar-benar valid, tanpa perlu API key/HMAC manual.
     */
    private static function testQrisAction(): Action
    {
        return Action::make('testQris')
            ->label('Test QRIS')
            ->color('gray')
            ->disabled(fn (QrisMerchant $record): bool => ! $record->projects()->where('is_active', true)->exists())
            ->tooltip(
                fn (QrisMerchant $record): ?string => $record->projects()->where('is_active', true)->exists()
                    ? null
                    : 'Pasang QRIS merchant ini ke project dulu di menu Projects.'
            )
            ->schema(fn (QrisMerchant $record): array => [
                Select::make('project_id')
                    ->label('Project')
                    ->options($record->projects()->where('is_active', true)->pluck('name', 'id'))
                    ->required(),
                TextInput::make('amount')
                    ->label('Nominal (Rp)')
                    ->helperText('Nominal dasar untuk invoice test.')
                    ->numeric()
                    ->minValue(1)
                    ->default(10000)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $project = Project::findOrFail($data['project_id']);

                try {
                    $invoice = app(InvoiceService::class)->create($project, [
                        'external_id' => 'TEST-'.Str::upper(Str::random(8)),
                        'amount' => (int) $data['amount'],
                        'description' => 'Test QRIS dari dashboard admin',
                        'customer_name' => 'Test Admin',
                    ]);

                    Notification::make()
                        ->title('Invoice test dibuat')
                        ->body('Buka halaman pembayaran untuk scan QR-nya.')
                        ->success()
                        ->persistent()
                        ->actions([
                            Action::make('open')
                                ->label('Buka halaman pembayaran')
                                ->url(route('invoices.show', $invoice), shouldOpenInNewTab: true)
                                ->button(),
                        ])
                        ->send();
                } catch (NoAvailableAmountException $e) {
                    Notification::make()
                        ->title('Gagal membuat invoice test')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
