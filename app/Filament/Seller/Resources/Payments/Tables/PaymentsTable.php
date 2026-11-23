<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Payments\Tables;

use App\Enums\InvoiceStatus;
use App\Exceptions\ConfirmedAmountMismatchException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\LatePaymentAmountConflictException;
use App\Exceptions\LatePaymentReasonRequiredException;
use App\Models\Invoice;
use App\Models\QrisMerchant;
use App\Models\User;
use App\Services\InvoicePaymentConfirmationService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Kartu pembayaran untuk penjual. Bahasa sehari-hari, tanpa istilah teknis.
 * Konfirmasi tetap lewat InvoicePaymentConfirmationService (nominal wajib
 * cocok, audit log, callback) — di sini hanya tampilan dan teksnya.
 */
final class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    TextColumn::make('payment_amount')
                        ->formatStateUsing(fn (int $state): string => self::rupiah($state))
                        ->description(fn (Invoice $record): string => 'Termasuk kode unik '.$record->unique_code)
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold)
                        ->searchable(),
                    TextColumn::make('customer_name')
                        ->placeholder('Pelanggan tanpa nama')
                        ->description(fn (Invoice $record): ?string => $record->description)
                        ->searchable(),
                    TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn (InvoiceStatus $state): string => self::statusLabel($state))
                        ->color(fn (InvoiceStatus $state): string => match ($state) {
                            InvoiceStatus::Paid => 'success',
                            InvoiceStatus::WaitingPayment, InvoiceStatus::ManualVerification => 'warning',
                            InvoiceStatus::Cancelled, InvoiceStatus::Failed => 'danger',
                            InvoiceStatus::Expired, InvoiceStatus::Created => 'gray',
                        }),
                    TextColumn::make('created_at')
                        ->label('Dibuat')
                        ->since()
                        ->color('gray')
                        ->size(TextSize::Small),
                ])->space(2),
            ])
            ->deferFilters(false)
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('period')
                    ->label('Periode')
                    ->options([
                        'today' => 'Hari ini',
                        'week' => '7 hari terakhir',
                        'month' => 'Bulan ini',
                    ])
                    ->placeholder('Semua waktu')
                    ->query(function (Builder $query, array $data): Builder {
                        $from = match ($data['value'] ?? null) {
                            'today' => now()->startOfDay(),
                            'week' => now()->subDays(6)->startOfDay(),
                            'month' => now()->startOfMonth(),
                            default => null,
                        };

                        if ($from === null) {
                            return $query;
                        }

                        // Yang sudah dibayar dihitung dari waktu uang masuk,
                        // sisanya dari waktu pembayaran dibuat.
                        return $query->where(fn (Builder $q): Builder => $q
                            ->where('paid_at', '>=', $from)
                            ->orWhere(fn (Builder $q): Builder => $q->whereNull('paid_at')->where('created_at', '>=', $from)));
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                self::confirmAction(),
            ])
            ->emptyStateHeading('Belum ada pembayaran di sini')
            ->emptyStateDescription('Pembayaran dari pelanggan akan muncul di daftar ini.')
            ->poll('30s');
    }

    private static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Uang sudah masuk')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status->canTransitionTo(InvoiceStatus::Paid)
                && self::belongsToCurrentShop($record))
            ->modalHeading('Konfirmasi uang masuk')
            ->modalDescription('Buka aplikasi GoPay Merchant dan pastikan uangnya benar-benar sudah masuk dengan jumlah yang sama persis. Konfirmasi yang sudah ditekan tidak bisa dibatalkan.')
            ->modalSubmitActionLabel('Ya, uang sudah masuk')
            ->schema(fn (Invoice $record): array => [
                Placeholder::make('amount_display')
                    ->label('Jumlah yang harus masuk')
                    ->content(self::rupiah($record->payment_amount))
                    ->helperText('Sudah termasuk kode unik di angka terakhir. Jumlah di GoPay Merchant harus sama persis.'),
                TextInput::make('confirmed_amount')
                    ->label('Jumlah uang yang masuk')
                    ->helperText('Ketik ulang jumlah seperti yang tertulis di GoPay Merchant, tanpa titik. Contoh: 100123.')
                    ->numeric()
                    ->integer()
                    ->required(),
                TextInput::make('confirmation_reference')
                    ->label('Nomor referensi (RRN)')
                    ->helperText('Buka transaksinya di GoPay Merchant, lalu salin "Nomor referensi". Ini jadi bukti pembayaran mana yang dicocokkan.')
                    ->required(),
                DateTimePicker::make('paid_at')
                    ->label('Waktu uang masuk')
                    ->default(now())
                    ->helperText('Sudah terisi waktu sekarang. Ubah kalau uangnya masuk lebih awal, sesuai jam di GoPay Merchant.')
                    ->required(),
                Textarea::make('confirmation_note')
                    ->label('Catatan')
                    ->helperText(
                        $record->status === InvoiceStatus::Expired
                            ? 'Wajib diisi. Pembayaran ini sudah lewat batas waktu, tulis alasannya. Contoh: "pelanggan bayar 1 menit setelah batas waktu".'
                            : 'Boleh dikosongkan.'
                    )
                    ->required($record->status === InvoiceStatus::Expired)
                    ->columnSpanFull(),
            ])
            ->action(function (Invoice $record, array $data): void {
                $user = auth()->user();

                // Cadangan di atas scope tenancy: jangan pernah mengonfirmasi
                // invoice milik toko lain, apa pun yang dikirim klien.
                if (! $user instanceof User || ! self::belongsToCurrentShop($record)) {
                    abort(403);
                }

                try {
                    app(InvoicePaymentConfirmationService::class)->confirm(
                        $record,
                        $user,
                        [
                            'confirmed_amount' => (int) $data['confirmed_amount'],
                            'confirmation_reference' => (string) $data['confirmation_reference'],
                            'paid_at' => Carbon::parse($data['paid_at']),
                            'confirmation_note' => $data['confirmation_note'] ?? null,
                        ],
                    );

                    Notification::make()
                        ->title('Uang masuk sudah dikonfirmasi')
                        ->success()
                        ->send();
                } catch (ConfirmedAmountMismatchException) {
                    self::reject('Jumlah yang Anda ketik tidak sama dengan jumlah pada kartu ('.self::rupiah($record->payment_amount).'). Periksa lagi di GoPay Merchant, terutama angka terakhirnya (kode unik).');
                } catch (LatePaymentReasonRequiredException) {
                    self::reject('Pembayaran ini sudah lewat batas waktu. Tulis catatan alasannya dulu.');
                } catch (LatePaymentAmountConflictException) {
                    self::reject('Jumlah ini sudah dipakai pembayaran lain yang masih aktif, jadi tidak bisa dipastikan uang itu milik pembayaran yang mana. Hubungi admin untuk menyelesaikannya.');
                } catch (InvalidStatusTransitionException) {
                    self::reject('Pembayaran ini sudah tidak bisa dikonfirmasi (mungkin sudah dibayar atau dibatalkan). Muat ulang halaman untuk melihat kondisi terbaru.');
                }
            });
    }

    private static function belongsToCurrentShop(Invoice $invoice): bool
    {
        $shop = Filament::getTenant();

        return $shop instanceof QrisMerchant && (int) $invoice->qris_merchant_id === (int) $shop->getKey();
    }

    private static function reject(string $message): void
    {
        Notification::make()
            ->title('Konfirmasi ditolak')
            ->body($message)
            ->danger()
            ->send();
    }

    private static function statusLabel(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Created => 'Disiapkan',
            InvoiceStatus::WaitingPayment => 'Menunggu pembayaran',
            InvoiceStatus::ManualVerification => 'Sedang dicek',
            InvoiceStatus::Paid => 'Sudah dibayar',
            InvoiceStatus::Expired => 'Kedaluwarsa',
            InvoiceStatus::Cancelled => 'Dibatalkan',
            InvoiceStatus::Failed => 'Gagal',
        };
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
