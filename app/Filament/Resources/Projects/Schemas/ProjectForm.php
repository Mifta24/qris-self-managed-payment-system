<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\QrisMerchant;
use App\Support\SigningSecretGenerator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * `notification_config` sengaja tidak ditampilkan — kolomnya ada di skema
 * tapi belum dipakai (notifikasi Telegram Fase 4 pakai satu chat admin
 * global, bukan per-project). Jangan tampilkan field yang tidak berfungsi.
 */
class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated()
                    ->helperText('Dipakai sebagai project_id di kontrak API. Tidak bisa diubah setelah dibuat.'),
                TextInput::make('name')
                    ->required()
                    ->helperText('Nama tampilan project di dashboard admin. Tidak dipakai di kontrak API.'),
                Select::make('qris_merchant_id')
                    ->label('QRIS Merchant')
                    ->options(fn (): array => QrisMerchant::query()->pluck('label', 'id')->all())
                    ->required()
                    ->helperText('Payload QRIS statis yang dipakai untuk membuat invoice project ini.'),
                TextInput::make('callback_url')
                    ->url()
                    ->helperText('URL project yang dipanggil sistem saat status invoice berubah (mis. jadi paid). Dikirim dengan header X-Qris-Signature yang ditandatangani pakai callback signing secret di bawah. Kosongkan kalau project tidak perlu notifikasi callback.'),
                TextInput::make('callback_signing_secret')
                    ->label('Callback signing secret')
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated()
                    ->default(fn (): string => app(SigningSecretGenerator::class)->generate())
                    ->copyable()
                    ->helperText('Dipakai project untuk verifikasi X-Qris-Signature pada callback. Tidak bisa diubah setelah dibuat.'),
                Toggle::make('is_active')
                    ->required()
                    ->default(true)
                    ->helperText('Nonaktifkan untuk menolak semua permintaan API project ini (respons project_inactive), tanpa menghapus datanya atau invoice yang sudah ada.'),
                TextInput::make('invoice_ttl_minutes')
                    ->label('TTL invoice (menit)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(30)
                    ->helperText('Berapa lama invoice baru boleh menunggu pembayaran sebelum otomatis berstatus expired oleh scheduler.'),
                TextInput::make('unique_code_min')
                    ->label('Kode unik minimum')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(1)
                    ->helperText('Batas bawah digit tambahan yang dijumlahkan ke nominal dasar supaya total pembayaran tiap invoice aktif berbeda-beda (memudahkan admin mencocokkan mutasi masuk).'),
                TextInput::make('unique_code_max')
                    ->label('Kode unik maksimum')
                    ->required()
                    ->numeric()
                    ->gte('unique_code_min')
                    ->default(999)
                    ->helperText('Batas atas kode unik. Rentang min–max menentukan berapa banyak invoice aktif dengan nominal dasar sama yang bisa dibedakan sebelum kehabisan kombinasi.'),
            ]);
    }
}
