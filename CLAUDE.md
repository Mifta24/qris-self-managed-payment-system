# CLAUDE.md

Panduan kerja untuk Claude Code di repositori ini. Baca sampai habis sebelum
menulis kode apa pun.

## Project ini apa

QRIS Self-Managed Payment System: API pembayaran internal terpusat yang dipakai
beberapa project internal. Sistem membuat invoice, menyisipkan nominal ke
payload QRIS statis GoPay Merchant, menampilkan halaman pembayaran, lalu
menunggu admin mengonfirmasi pembayaran secara manual.

**Yang sistem ini BUKAN:** ini bukan payment gateway. Tidak ada webhook dari
GoPay, tidak ada pengecekan saldo, tidak ada refund otomatis, tidak ada
verifikasi otomatis. Status `paid` HANYA bisa dihasilkan oleh tindakan manusia.
Kalau sebuah rancangan mengandaikan sistem tahu sendiri bahwa uang sudah masuk,
rancangan itu salah.

## Aturan mutlak

Aturan berikut tidak boleh dilanggar tanpa persetujuan eksplisit pemilik repo.
Kalau sebuah permintaan bertabrakan dengan salah satunya, **berhenti dan
tanyakan**, jangan cari jalan pintas.

1. **Uang selalu integer.** Rupiah penuh, `bigint` di database, cast `integer`
   di model. Tidak ada `float`, tidak ada `decimal`, tidak ada perhitungan
   nominal dalam JavaScript.
2. **Invoice yang sudah `paid` bersifat final.** Nominal, kode unik, payload,
   dan project-nya tidak boleh berubah lagi dengan cara apa pun.
3. **Kode unik dialokasikan lewat unique constraint database, bukan `SELECT`
   dulu.** Pola `SELECT ... WHERE amount = ?` lalu `INSERT` adalah race
   condition. Selalu: `INSERT` di dalam transaksi, tangkap unique violation,
   retry.
4. **CRC tidak pernah ditulis manual.** Payload QRIS hanya boleh dihasilkan
   lewat `QrisPayload::toString()` atau `DynamicQrisGenerator`.
5. **Payload QRIS utama tidak pernah keluar dari server.** Tidak masuk log,
   tidak masuk response API, tidak masuk halaman pembayaran. Yang boleh dikirim
   ke customer hanya payload turunan milik invoice tersebut.
6. **Setiap perubahan status invoice menulis audit log.** Tanpa pengecualian,
   termasuk perubahan yang dipicu scheduler.
7. **Jangan bikin endpoint yang mengubah status invoice jadi `paid` lewat API
   project.** Konfirmasi hanya dari dashboard admin oleh user yang login.
8. **Jangan pasang library QRIS pihak ketiga.** Parser dan CRC ditulis sendiri
   di `app/Support/Qris` supaya bisa diaudit. Lihat `docs/qris-spec.md`.

## Stack

- Laravel + PHP 8.3, `declare(strict_types=1)` di semua file
- PostgreSQL (partial unique index dipakai untuk kunci nominal)
- Laravel Queue dengan driver `database` (Redis + Horizon belum terpasang),
  Laravel Scheduler untuk expiry
- Filament untuk dashboard admin
- Blade + Alpine + Tailwind untuk halaman pembayaran publik
- Pest untuk test
- `bacon/bacon-qr-code` untuk render QR (SVG, tidak disimpan sebagai file)

## Peta folder

```
app/Support/Qris/         Parser TLV, CRC16, generator QRIS dinamis. Sudah jadi
                          dan sudah ada test. Jangan diubah tanpa alasan kuat.
app/Services/             Logika bisnis (InvoiceService, UniqueCodeAllocator, ...)
app/Http/Controllers/Api/ Endpoint v1 untuk project
app/Filament/             Dashboard admin
database/migrations/      Skema. Lihat docs/database.md sebelum menambah kolom.
tests/Feature/Qris/       Test parser dan generator
docs/                     Dokumen rujukan per topik
```

## Dokumen rujukan

Baca yang relevan sebelum mengerjakan, jangan menebak:

| Tugas | Baca |
|---|---|
| Menyentuh payload QRIS, tag, atau CRC | `docs/qris-spec.md` |
| Menambah tabel, kolom, atau index | `docs/database.md` |
| Membuat atau mengubah endpoint, auth, callback | `docs/api-contract.md` |
| Alur invoice, transisi status, modul | `docs/architecture.md` |
| Gaya kode, struktur kelas, kebijakan test | `docs/conventions.md` |
| Menentukan apa yang dikerjakan berikutnya | `docs/roadmap.md` |
| Cara pakai dashboard admin atau integrasi API dari project lain | `docs/usage-guide.md` |

## Perintah

```bash
php artisan migrate                # PostgreSQL, lihat catatan MySQL di migration
php artisan test                   # seluruh test
php artisan test --filter=Qris     # hanya domain QRIS
./vendor/bin/pint                  # formatter
php artisan queue:work             # worker queue (dev)
php artisan schedule:work          # scheduler (dev)
```

## Cara kerja yang diharapkan

- **Rencanakan dulu untuk perubahan yang menyentuh uang atau status invoice.**
  Tulis rencananya, tunggu persetujuan, baru implementasi.
- **Test dulu untuk logika uang.** Alokasi kode unik, transisi status,
  perhitungan nominal, dan signature: tulis test yang gagal lebih dulu.
- **Perubahan kecil dan fokus.** Satu commit satu maksud.
- **Jangan bikin abstraksi untuk kasus yang belum ada.** Provider adapter baru
  dibuat saat provider kedua benar-benar masuk, bukan sekarang.
- **Kalau ragu soal aturan bisnis, tanya.** Menebak aturan pembayaran lebih
  mahal daripada bertanya.

## Yang jangan dilakukan tanpa ditanya dulu

- Mengubah apa pun di `app/Support/Qris/`
- Mengubah migration yang sudah jalan di produksi (buat migration baru)
- Menambah dependency baru ke `composer.json`
- Mengubah format response API v1 (project lain sudah bergantung padanya)
- Menghapus atau melonggarkan unique index pada nominal invoice aktif
- Menyentuh apa pun yang berkaitan dengan `signing_secret` atau enkripsi

## Bahasa

Identifier, nama kelas, nama kolom, dan pesan commit dalam bahasa Inggris.
Komentar kode, dokumen di `docs/`, dan pesan exception yang dilihat admin dalam
bahasa Indonesia.
