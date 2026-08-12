<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * Read-only. Invoice tidak pernah diedit lewat form admin — transisi status
 * cuma lewat InvoicePaymentConfirmationService (lihat action konfirmasi di
 * InvoicesTable). qris_payload sengaja tidak pernah ditampilkan di sini,
 * sesuai aturan mutlak #5 di CLAUDE.md.
 */
class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('invoice_number'),
                TextEntry::make('project.name')->label('Project'),
                TextEntry::make('external_id')
                    ->helperText('ID pesanan dari sisi project pemanggil. Kombinasi project + external_id unik — dipakai project untuk mencegah invoice dobel kalau permintaan dikirim ulang.'),
                TextEntry::make('status')->badge()
                    ->helperText('created → waiting_payment → paid/expired/cancelled/failed. manual_verification dipakai saat admin sedang mengecek sebelum konfirmasi. Status paid, cancelled, dan failed bersifat final.'),
                TextEntry::make('description'),
                TextEntry::make('customer_name'),
                TextEntry::make('customer_email'),
                TextEntry::make('customer_phone'),
                TextEntry::make('base_amount')
                    ->label('Nominal dasar')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.'))
                    ->helperText('Nominal yang diminta project, sebelum ditambah kode unik.'),
                TextEntry::make('unique_code')->label('Kode unik')
                    ->helperText('Digit tambahan di atas nominal dasar supaya total pembayaran invoice ini berbeda dari invoice aktif lain dengan nominal dasar sama.'),
                TextEntry::make('payment_amount')
                    ->label('Total pembayaran')
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.'))
                    ->helperText('Nominal dasar + kode unik. Ini jumlah yang harus benar-benar masuk sebelum admin konfirmasi paid.'),
                TextEntry::make('expires_at')->dateTime(),
                TextEntry::make('paid_at')->dateTime(),
                TextEntry::make('confirmedBy.name')->label('Dikonfirmasi oleh')
                    ->helperText('Admin yang menekan tombol konfirmasi pembayaran. Status paid hanya bisa dihasilkan lewat tindakan manual ini, tidak pernah otomatis.'),
                TextEntry::make('confirmed_at')->dateTime(),
                TextEntry::make('confirmation_reference')->label('RRN')
                    ->helperText('Reference/Retrieval Number dari mutasi bank atau e-wallet, dicatat admin saat konfirmasi sebagai jejak pembayaran mana yang dicocokkan.'),
                TextEntry::make('confirmation_note')->label('Catatan konfirmasi')
                    ->helperText('Catatan bebas dari admin saat konfirmasi, misalnya alasan kalau nominal atau waktu pembayaran tidak persis cocok.'),
                TextEntry::make('cancelled_at')->dateTime(),
                TextEntry::make('cancellation_reason')
                    ->helperText('Alasan invoice dibatalkan, diisi manual oleh admin atau otomatis oleh scheduler untuk invoice yang kedaluwarsa.'),
                TextEntry::make('created_at')->dateTime(),
                RepeatableEntry::make('callbackLogs')
                    ->label('Riwayat callback')
                    ->helperText('Percobaan pengiriman notifikasi perubahan status ke callback_url project, termasuk retry otomatis kalau percobaan sebelumnya gagal.')
                    ->state(fn (Invoice $record) => $record->callbackLogs)
                    ->contained(false)
                    ->table([
                        TableColumn::make('Percobaan'),
                        TableColumn::make('Event'),
                        TableColumn::make('Status'),
                        TableColumn::make('Terkirim pada'),
                        TableColumn::make('Percobaan berikutnya'),
                        TableColumn::make('Error'),
                    ])
                    ->schema([
                        TextEntry::make('attempt'),
                        TextEntry::make('event_type'),
                        TextEntry::make('response_status')->placeholder('-'),
                        TextEntry::make('succeeded_at')->dateTime()->placeholder('-'),
                        TextEntry::make('next_retry_at')->dateTime()->placeholder('-'),
                        TextEntry::make('error_message')->placeholder('-'),
                    ])
                    ->placeholder('Belum ada percobaan callback untuk invoice ini.')
                    ->columnSpanFull(),
            ]);
    }
}
