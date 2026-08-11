<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Exceptions\ConfirmedAmountMismatchException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\LatePaymentAmountConflictException;
use App\Exceptions\LatePaymentReasonRequiredException;
use App\Models\Invoice;
use App\Services\InvoicePaymentConfirmationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Ini antrian verifikasi, bukan CRUD invoice biasa — lihat docs/roadmap.md
 * Fase 4. Tidak ada EditAction/DeleteAction: invoice cuma boleh berubah
 * status lewat InvoicePaymentConfirmationService, dan tidak pernah dihapus.
 */
class InvoicesTable
{
    private const QUEUE_STATUSES = [
        InvoiceStatus::Created,
        InvoiceStatus::WaitingPayment,
        InvoiceStatus::ManualVerification,
        InvoiceStatus::Expired,
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('base_amount')
                    ->label('Nominal dasar')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.')),
                TextColumn::make('unique_code')
                    ->label('Kode unik'),
                TextColumn::make('payment_amount')
                    ->label('Total pembayaran')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.'))
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::Paid => 'success',
                        InvoiceStatus::WaitingPayment, InvoiceStatus::ManualVerification => 'warning',
                        InvoiceStatus::Expired => 'gray',
                        InvoiceStatus::Cancelled, InvoiceStatus::Failed => 'danger',
                        InvoiceStatus::Created => 'gray',
                    }),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('callback_failed_at')
                    ->label('Callback')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (): string => 'Gagal')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payment_amount')
            ->filters([
                Filter::make('menunggu_konfirmasi')
                    ->label('Menunggu konfirmasi')
                    ->query(fn (Builder $query): Builder => $query->whereIn(
                        'status',
                        array_map(fn (InvoiceStatus $status): string => $status->value, self::QUEUE_STATUSES),
                    ))
                    ->default(),
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
                Filter::make('callback_gagal')
                    ->label('Callback gagal total')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('callback_failed_at')),
            ])
            ->recordActions([
                ViewAction::make(),
                self::confirmAction(),
            ]);
    }

    private static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Konfirmasi')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status->canTransitionTo(InvoiceStatus::Paid))
            ->schema(fn (Invoice $record): array => [
                Placeholder::make('payment_amount_display')
                    ->label('Total pembayaran')
                    ->content('Rp '.number_format($record->payment_amount, 0, ',', '.')),
                TextInput::make('confirmed_amount')
                    ->label('Nominal yang diterima')
                    ->helperText('Ketik ulang nominal yang tertera di mutasi GoPay Merchant.')
                    ->numeric()
                    ->required(),
                TextInput::make('confirmation_reference')
                    ->label('RRN / nomor referensi')
                    ->required(),
                DateTimePicker::make('paid_at')
                    ->label('Waktu bayar')
                    ->default(now())
                    ->required(),
                Textarea::make('confirmation_note')
                    ->label('Catatan')
                    ->helperText(
                        $record->status === InvoiceStatus::Expired
                            ? 'Wajib diisi: invoice ini sudah kedaluwarsa, jelaskan kenapa tetap dikonfirmasi.'
                            : 'Opsional.'
                    )
                    ->required($record->status === InvoiceStatus::Expired)
                    ->columnSpanFull(),
            ])
            ->action(function (Invoice $record, array $data): void {
                try {
                    app(InvoicePaymentConfirmationService::class)->confirm(
                        $record,
                        auth()->user(),
                        [
                            'confirmed_amount' => (int) $data['confirmed_amount'],
                            'confirmation_reference' => $data['confirmation_reference'],
                            'paid_at' => Carbon::parse($data['paid_at']),
                            'confirmation_note' => $data['confirmation_note'] ?? null,
                        ],
                    );

                    Notification::make()
                        ->title('Pembayaran dikonfirmasi')
                        ->success()
                        ->send();
                } catch (
                    ConfirmedAmountMismatchException
                    |InvalidStatusTransitionException
                    |LatePaymentReasonRequiredException
                    |LatePaymentAmountConflictException $e
                ) {
                    Notification::make()
                        ->title('Konfirmasi ditolak')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
