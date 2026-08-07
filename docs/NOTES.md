# QRIS Payment System — Fondasi (skema DB + parser QRIS)

Semua file di sini mengikuti struktur Laravel standar. Salin isi folder ini ke
root project, lalu jalankan `php artisan migrate`.

```
database/migrations/   6 migration
app/Support/Qris/      Crc16, TlvNode, QrisPayload, DynamicQrisGenerator
tests/Support/         helper pembuat payload untuk test
tests/Feature/Qris/    3 file test Pest
```

## Verifikasi

Logika parser, CRC, dan generator sudah dijalankan dan lolos seluruh assertion
yang ada di file test (PHP 8.3), termasuk check value resmi CRC-16/CCITT-FALSE
`"123456789" => 29B1`.

Yang belum bisa diuji dari sini: apakah QR hasil generate benar-benar diterima
aplikasi pembayar. Itu hanya bisa dipastikan dengan scan nyata — lihat bagian
"Sebelum produksi" di bawah.

## Keputusan yang perlu kamu ketahui

**Tag 01 dibiarkan `11` secara default.** Secara EMVCo, QR bernominal seharusnya
ditandai `12` (dinamis), tapi sebagian aplikasi bank memvalidasi bahwa QR
dinamis berasal dari PJP dan menolak QR statis yang ditandai dinamis.
Default-nya tidak mengubah apa-apa; aktifkan lewat
`new DynamicQrisGenerator(markAsDynamic: true)` kalau hasil uji scan menunjukkan
sebaliknya.

**Tag 55/56/57 (tip) dibuang.** Kalau payload statis membawa tip indicator,
sebagian aplikasi tetap meminta pengguna menambah nominal — dan itu merusak
seluruh premis pencocokan lewat kode unik.

**CRC selalu dihitung ulang di `toString()`.** Tag 63 tidak pernah ikut
diserialisasi sebagai node biasa, jadi tidak mungkin menghasilkan payload dengan
CRC basi. Payload sumber juga wajib lolos cek CRC sebelum dimodifikasi — kalau
sumbernya sudah rusak, hasilnya pasti salah.

**Template bersarang di-parse, tapi gagal parse tidak menggagalkan payload.**
Tag 26–51, 62, 64, 80–99 diurai jadi anak TLV. Kalau ada acquirer yang isinya
tidak patuh format, node itu diperlakukan sebagai value mentah — bukan alasan
menolak seluruh QR.

**`signing_secret` di tabel `api_keys` disimpan terenkripsi, bukan di-hash.**
Ini sering keliru. API key boleh di-hash satu arah (cukup dibandingkan), tapi
secret untuk HMAC harus bisa dibaca balik karena server perlu menghitung ulang
signature. Pakai cast `encrypted` di model, jangan `hashed`.

**Kunci nominal invoice aktif** ada di migration `invoices` sebagai partial
unique index (PostgreSQL) atau generated column + unique key (MySQL 8).
Ini pengaman terpenting di seluruh sistem: tanpa ini, dua invoice aktif bisa
punya total sama dan admin berpotensi menandai invoice yang salah sebagai lunas.

**`paid_at` dan `confirmed_at` dipisah.** Yang pertama waktu pembayaran menurut
GoPay Merchant (diinput admin), yang kedua waktu admin menekan tombol. Kalau
disatukan, rekonsiliasi dan audit jadi tidak bisa dipercaya.

## Model yang perlu dibuat

Cast yang wajib ada:

```php
// QrisMerchant
protected function casts(): array
{
    return ['static_payload' => 'encrypted'];
}

// ApiKey
protected function casts(): array
{
    return ['signing_secret' => 'encrypted', 'allowed_ips' => 'array'];
}

// Invoice
protected function casts(): array
{
    return [
        'base_amount' => 'integer',
        'unique_code' => 'integer',
        'payment_amount' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
```

Casting integer eksplisit untuk semua kolom nominal itu penting — PHP terlalu
permisif soal string-to-number, dan ini menyentuh uang.

## Langkah berikutnya

1. **`UniqueCodeAllocator`** — pengalokasi kode unik. Bentuknya: loop dalam
   `DB::transaction`, coba kode acak dalam rentang project, tangkap
   `QueryException` pada unique violation, ulangi sampai batas percobaan, lalu
   lempar `NoAvailableAmountException` kalau rentangnya habis. Jangan cek
   ketersediaan dengan `SELECT` lebih dulu — itu race condition; biarkan
   database yang jadi wasit.
2. **`InvoiceService::create()`** — merangkai allocator + generator + penyimpanan
   dalam satu transaksi.
3. **Middleware autentikasi API** — lookup `key_prefix`, verifikasi `key_hash`,
   lalu verifikasi HMAC atas raw body + timestamp (tolak timestamp lebih dari
   ~5 menit untuk mencegah replay).
4. **Renderer QR** — `bacon/bacon-qr-code`, output SVG, di-cache berdasarkan
   hash payload. Jangan simpan file.

## Sebelum produksi

Uji scan payload hasil generate di minimal: Gojek, DANA, OVO, ShopeePay, BCA
mobile, dan Livin. Perilaku antar aplikasi berbeda, terutama soal tag 01.
Isi `QRIS_TEST_STATIC_PAYLOAD` di `.env.testing` dengan payload asli agar golden
test di `DynamicQrisGeneratorTest` ikut berjalan.
