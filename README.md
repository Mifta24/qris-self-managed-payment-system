# QRIS Self-Managed Payment System

API pembayaran internal terpusat untuk beberapa project internal. Sistem
membuat invoice, menyisipkan nominal ke payload QRIS statis GoPay Merchant,
menampilkan halaman pembayaran publik, lalu menunggu **admin mengonfirmasi
pembayaran secara manual** lewat dashboard.

> **Ini bukan payment gateway.** Tidak ada webhook dari GoPay, tidak ada
> pengecekan saldo, tidak ada refund otomatis, dan tidak ada verifikasi
> otomatis. Status `paid` hanya bisa dihasilkan oleh tindakan manusia.

## Cara kerja singkat

1. Project lain memanggil `POST /api/v1/invoices` (API key + signature HMAC).
2. Sistem mengalokasikan **kode unik** (default 1-999) dan menambahkannya ke
   nominal dasar, mis. `100000` menjadi `100237`, lalu membuat payload QRIS
   dinamis dengan nominal tersebut.
3. Customer membuka `payment_url`, scan QR, dan membayar.
4. Admin mengecek mutasi di aplikasi GoPay Merchant, mencari invoice dengan
   `payment_amount` yang cocok di dashboard, mengisi RRN dan waktu bayar, lalu
   mengonfirmasi.
5. Sistem menandai invoice `paid`, menulis audit log, dan mengirim callback
   bertanda tangan ke project.

Alur lengkap dan tabel transisi status ada di [docs/architecture.md](docs/architecture.md).

## Fitur

- **API v1** untuk project: create, detail, status, cancel, dan list
  transactions. Autentikasi API key + HMAC, idempotency per `external_id`.
- **Kode unik** dialokasikan lewat unique constraint PostgreSQL (partial
  unique index), bukan `SELECT` lalu `INSERT`.
- **Parser TLV, CRC16, dan generator QRIS dinamis** ditulis sendiri di
  `app/Support/Qris` (tanpa library QRIS pihak ketiga) supaya bisa diaudit.
- **Halaman pembayaran publik** (Blade + Alpine + Tailwind): QR SVG,
  countdown, dan polling status.
- **Dashboard admin** (Filament): QRIS Merchants, Projects, API Keys, dan
  antrian verifikasi.
- **Callback** ke project saat invoice `paid`, `expired`, atau `cancelled`,
  dengan retry dan signature.
- **Audit log** untuk setiap perubahan status invoice, termasuk yang dipicu
  scheduler.

## Stack

- PHP 8.3, Laravel 13, `declare(strict_types=1)` di semua file
- PostgreSQL
- Redis + Horizon (queue), Laravel Scheduler (expiry)
- Filament 4 (dashboard admin)
- Blade + Alpine + Tailwind (halaman pembayaran)
- Pest (test)
- `bacon/bacon-qr-code` (render QR sebagai SVG, tidak disimpan sebagai file)

## Memulai

Prasyarat: PHP 8.3+, Composer, Node.js, PostgreSQL, Redis.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Atur koneksi database dan queue di `.env` (bawaan `.env.example` masih
SQLite dan queue `database`; sistem ini dirancang untuk PostgreSQL dan Redis):

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=qris
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=redis
```

Lalu:

```bash
php artisan migrate
npm run build
```

Buat user admin untuk login ke dashboard Filament
(`php artisan make:filament-user`), lalu buka `/admin`.

### Menjalankan di lokal

```bash
php artisan serve          # aplikasi
php artisan horizon        # worker queue
php artisan schedule:work  # scheduler (expiry invoice)
npm run dev                # asset
```

### Test dan formatter

```bash
php artisan test                  # seluruh test
php artisan test --filter=Qris    # hanya domain QRIS
./vendor/bin/pint                 # formatter
```

## Setup pertama kali di dashboard

Urutan untuk project baru (langkah detail di [docs/usage-guide.md](docs/usage-guide.md)):

1. **QRIS Merchants**: tambah merchant dan isi payload QRIS statis (bisa scan
   dari foto stiker). Payload disimpan terenkripsi dan tidak pernah
   ditampilkan kembali.
2. **Projects**: buat project, hubungkan ke merchant, atur `callback_url`,
   TTL invoice, dan rentang kode unik.
3. **API Keys**: buat key untuk project. `X-Api-Key` dan signing secret hanya
   tampil **satu kali**.

## Endpoint API v1

| Method | Path | Fungsi |
|---|---|---|
| `POST` | `/api/v1/invoices` | Buat invoice |
| `GET` | `/api/v1/invoices/{invoice}` | Detail invoice |
| `GET` | `/api/v1/invoices/{invoice}/status` | Status ringkas (polling) |
| `POST` | `/api/v1/invoices/{invoice}/cancel` | Batalkan invoice `waiting_payment` |
| `GET` | `/api/v1/transactions` | Daftar invoice (paginasi, filter `status`, `external_id`) |

Tidak ada endpoint untuk mengonfirmasi (`paid`) atau meng-expire invoice lewat
API. Itu disengaja.

Setiap request wajib membawa `X-Api-Key`, `X-Timestamp`, dan `X-Signature`
(HMAC-SHA256 atas `METHOD`, `PATH`, `TIMESTAMP`, dan `sha256(RAW_BODY)`).
Contoh kode penandatanganan (Node.js dan PHP), format response, error, dan
verifikasi callback ada di [docs/usage-guide.md](docs/usage-guide.md) dan
[docs/api-contract.md](docs/api-contract.md).

## Struktur folder

```
app/Support/Qris/          Parser TLV, CRC16, generator QRIS dinamis
app/Services/              InvoiceService, UniqueCodeAllocator, expiry,
                           pembatalan, konfirmasi pembayaran
app/Http/Controllers/Api/  Endpoint v1 untuk project
app/Filament/              Dashboard admin
database/migrations/       Skema database
tests/Feature/Qris/        Test parser dan generator
docs/                      Dokumen rujukan per topik
```

## Dokumentasi

| Topik | Dokumen |
|---|---|
| Spesifikasi payload QRIS, tag, CRC | [docs/qris-spec.md](docs/qris-spec.md) |
| Skema database dan index | [docs/database.md](docs/database.md) |
| Kontrak API, auth, callback | [docs/api-contract.md](docs/api-contract.md) |
| Arsitektur, alur invoice, transisi status | [docs/architecture.md](docs/architecture.md) |
| Gaya kode dan kebijakan test | [docs/conventions.md](docs/conventions.md) |
| Rencana pengerjaan | [docs/roadmap.md](docs/roadmap.md) |
| Panduan admin dan integrasi API | [docs/usage-guide.md](docs/usage-guide.md) |

## Aturan penting

Aturan berikut tidak boleh dilanggar tanpa persetujuan pemilik repo (lengkapnya
di [CLAUDE.md](CLAUDE.md)):

1. Uang selalu integer (rupiah penuh, `bigint`), tanpa `float` atau `decimal`.
2. Invoice `paid` bersifat final dan tidak bisa diubah lagi.
3. Kode unik dialokasikan lewat unique constraint database, dengan retry saat
   terjadi unique violation.
4. CRC tidak pernah ditulis manual; payload hanya lewat `QrisPayload` atau
   `DynamicQrisGenerator`.
5. Payload QRIS utama tidak pernah keluar dari server (log, API, maupun
   halaman pembayaran).
6. Setiap perubahan status invoice menulis audit log.
7. Tidak ada endpoint API project yang mengubah status menjadi `paid`.
8. Tidak memakai library QRIS pihak ketiga.

## Batasan yang disengaja

- Bottleneck sistem adalah manusia: tidak ada verifikasi otomatis.
- Jumlah invoice aktif per merchant dibatasi rentang kode unik (default
  1-999).
- Pembatasan `allowed_ips` pada API key tersimpan tetapi belum difilter di
  server.
