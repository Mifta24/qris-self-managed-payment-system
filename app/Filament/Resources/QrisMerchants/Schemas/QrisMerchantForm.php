<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrisMerchants\Schemas;

use App\Support\Qris\QrisPayload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class QrisMerchantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->helperText('Slug unik untuk identifikasi merchant ini, huruf kecil dan tanda hubung. Bukan dipakai project untuk relasi (itu pakai ID internal) — murni memudahkan admin mengenali merchant mana yang sedang dilihat. Contoh: gopay-utama.'),
                TextInput::make('label')
                    ->required()
                    ->helperText('Nama yang tampil di daftar merchant pada dashboard admin. Bebas, tidak harus sama dengan merchant_name di payload.'),
                ViewField::make('static_payload_scan')
                    ->view('filament.forms.components.qris-photo-scan')
                    ->label('Scan dari foto QRIS')
                    ->helperText('Opsional. Ambil atau unggah foto stiker QRIS, lalu payload di bawah akan terisi otomatis untuk diperiksa sebelum disimpan.')
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Textarea::make('static_payload')
                    ->label('Payload QRIS statis')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $payload = filled($state) ? QrisPayload::tryParse($state) : null;

                        if ($payload === null) {
                            return;
                        }

                        if (filled($merchantName = $payload->merchantName())) {
                            $set('merchant_name', $merchantName);
                        }

                        if (filled($merchantCity = $payload->merchantCity())) {
                            $set('merchant_city', $merchantCity);
                        }

                        if (filled($currency = $payload->currency())) {
                            $set('currency', $currency);
                        }
                    })
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->afterStateHydrated(fn ($component) => $component->state(null))
                    ->helperText('Kosongkan untuk mempertahankan payload yang sudah ada. Isi hanya untuk menggantinya. Nilai yang tersimpan tidak pernah ditampilkan balik ke sini.')
                    ->columnSpanFull(),
                TextInput::make('merchant_name')
                    ->label('Nama merchant')
                    ->helperText('Terisi otomatis dari tag 59 payload QRIS saat payload diisi. Boleh ditimpa manual kalau ingin ditampilkan beda dari payload asli.'),
                TextInput::make('merchant_city')
                    ->label('Kota merchant')
                    ->helperText('Terisi otomatis dari tag 60 payload QRIS saat payload diisi. Boleh ditimpa manual.'),
                TextInput::make('national_merchant_id')
                    ->label('National Merchant ID (NMID)')
                    ->helperText('Tidak bisa diisi otomatis dari payload. Biasanya tercetak di stiker QRIS fisik, di bawah atau di samping QR code, diawali huruf ID lalu 15 digit (contoh: ID1234567890123). Kosongkan kalau tidak tercantum di stiker.'),
                TextInput::make('currency')
                    ->required()
                    ->default('360')
                    ->helperText('Kode mata uang ISO 4217 numerik. 360 = Rupiah (IDR). Terisi otomatis dari tag 53 payload QRIS — hampir selalu tidak perlu diubah dari default.'),
                Toggle::make('is_active')
                    ->required()
                    ->default(true)
                    ->helperText('Menandai payload ini masih valid dan boleh dipakai. Nonaktifkan (tanpa menghapus datanya) kalau payload sudah tidak berlaku, misalnya stiker QRIS diganti.'),
                DateTimePicker::make('verified_at')
                    ->label('Terakhir diverifikasi')
                    ->helperText('Kapan payload ini terakhir diuji scan sungguhan.'),
            ]);
    }
}
