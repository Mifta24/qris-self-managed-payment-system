<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Exceptions\ConfirmedAmountMismatchException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\LatePaymentAmountConflictException;
use App\Exceptions\LatePaymentReasonRequiredException;
use App\Models\Invoice;
use App\Models\Project;
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
use Filament\Tables\Grouping\Group;
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
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('unique_code')
                    ->label('Kode unik')
                    ->sortable()
                    ->searchable(),
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
            ->groups([
                Group::make('project.name')
                    ->label('Project')
                    ->collapsible(),
            ])
            ->defaultGroup('project.name')
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
                SelectFilter::make('project_id')
                    ->label('Project')
                    ->options(fn (): array => Project::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
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
            ->modalDescription('Buka mutasi masuk di aplikasi GoPay Merchant, lalu cocokkan satu per satu dengan data di bawah sebelum menekan konfirmasi. Salah isi nominal atau RRN bisa membuat konfirmasi ditolak sistem atau salah menandai invoice.')
            ->schema(fn (Invoice $record): array => [
                Placeholder::make('payment_amount_display')
                    ->label('Total pembayaran invoice')
                    ->content('Rp '.number_format($record->payment_amount, 0, ',', '.'))
                    ->helperText('Nominal ini yang wajib sama persis dengan mutasi masuk, termasuk kode uniknya. Bukan nominal yang perlu diketik ulang di bawah — cuma pembanding.'),
                TextInput::make('confirmed_amount')
                    ->label('Nominal yang diterima')
                    ->helperText('Ketik ulang nominal persis seperti yang tertera di mutasi GoPay Merchant. Kalau tidak sama dengan "Total pembayaran invoice" di atas, konfirmasi akan ditolak.')
                    ->numeric()
                    ->required(),
                TextInput::make('confirmation_reference')
                    ->label('RRN / nomor referensi')
                    ->helperText('Reference/Retrieval Number (RRN) dari detail mutasi GoPay Merchant — dipakai sebagai jejak pembayaran mana yang dicocokkan ke invoice ini.')
                    ->required(),
                DateTimePicker::make('paid_at')
                    ->label('Waktu bayar')
                    ->default(now())
                    ->helperText('Waktu uang benar-benar masuk menurut mutasi, bukan waktu sekarang saat form ini diisi. Nilai default cuma perkiraan awal, ubah kalau berbeda.')
                    ->required(),
                Textarea::make('confirmation_note')
                    ->label('Catatan')
                    ->helperText(
                        $record->status === InvoiceStatus::Expired
                            ? 'Wajib diisi: invoice ini sudah kedaluwarsa, jelaskan kenapa tetap dikonfirmasi (misalnya "customer bayar 40 detik setelah expired, mutasi sudah masuk").'
                            : 'Opsional. Isi kalau ada hal yang tidak persis cocok (nominal, waktu) tapi konfirmasi tetap dilakukan, supaya admin lain paham alasannya.'
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
