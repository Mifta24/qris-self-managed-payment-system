# Database

PostgreSQL. Migration ada di `database/migrations/`.

## Aturan umum

- Semua nominal: `bigint`, rupiah penuh, cast `integer` di model.
- Kolom waktu: `timestamp`, cast `datetime`.
- Kolom rahasia yang perlu dibaca balik: cast `encrypted`.
- Kolom rahasia yang cukup dibandingkan: simpan hash-nya.
- Tabel audit dan log bersifat append-only: tidak ada `updated_at`, tidak ada
  soft delete, tidak ada update.

## Tabel

### `qris_merchants`
Payload QRIS statis milik merchant. `static_payload` dienkripsi (cast
`encrypted`), bukan disimpan di `.env`, supaya bisa dirotasi tanpa deploy dan
tidak bocor lewat database dump. `verified_at` mencatat kapan terakhir payload
diuji scan.

### `projects`
Satu baris per project yang boleh memakai API. `slug` dipakai sebagai
`project_id` di request. Konfigurasi per project: TTL invoice, rentang kode
unik, callback URL, kanal notifikasi.

`callback_signing_secret` (encrypted) dipakai untuk menandatangani callback
KELUAR (`X-Qris-Signature`), terpisah dari `api_keys.signing_secret` yang
menandatangani request MASUK dari project. Satu project bisa punya beberapa
API key, jadi tidak ada satu `signing_secret` API key yang bisa dijadikan
acuan tunggal untuk callback.

### `api_keys`
Kredensial per project. Perhatikan pemisahan tiga kolom:

- `key_prefix` — bagian awal API key, di-index, dipakai untuk lookup satu query.
- `key_hash` — hash satu arah dari API key penuh, dipakai untuk autentikasi.
- `signing_secret` — **encrypted, bukan hashed**. Server perlu membacanya untuk
  menghitung ulang HMAC. Ini titik yang paling sering salah dirancang.

### `invoices`
Tabel inti.

- `payment_amount` disimpan, bukan dihitung ulang, karena ikut dikunci index.
- `(project_id, external_id)` unik → idempotency pembuatan invoice.
- `paid_at` = waktu pembayaran menurut GoPay Merchant (diinput admin).
  `confirmed_at` = waktu admin menekan tombol. **Jangan disatukan** — keduanya
  dibutuhkan untuk rekonsiliasi yang jujur.
- `callback_url` di-snapshot per invoice, tidak dibaca dari project saat kirim.
- `callback_failed_at` diisi `SendCallbackJob` setelah percobaan ke-6 gagal —
  penanda invoice butuh perhatian admin di dashboard.
- `qris_payload` menyimpan payload dinamis. Gambar QR tidak pernah disimpan
  sebagai file; dirender on the fly lalu dicache.

**Index paling penting:**

```sql
CREATE UNIQUE INDEX invoices_active_payment_amount_unique
ON invoices (qris_merchant_id, payment_amount)
WHERE status IN ('created', 'waiting_payment', 'manual_verification');
```

Ini yang mencegah dua invoice aktif punya total sama. Tanpa ini, verifikasi
manual bisa menandai invoice yang salah sebagai lunas. **Jangan pernah dihapus
atau dilonggarkan.** Di MySQL 8 padanannya generated column bernilai NULL saat
invoice tidak aktif — sudah ada di migration.

### `users` (kolom `is_admin`)
`is_admin` (boolean, default `false`) menentukan akses panel `/admin`. Migration
mengisi `true` untuk user yang sudah ada saat kolom ditambahkan, karena dulu
semua user adalah admin. Kolom ini tidak ada di `#[Fillable]` model `User`.

### `qris_merchant_user`
Pivot akun penjual ke toko (`qris_merchants`). Unik per pasangan. Dipakai panel
`/toko` (tenancy Filament per `QrisMerchant`) untuk membatasi data dan hak
konfirmasi ke toko milik akun tersebut. `cascadeOnDelete` di kedua sisi.

### `callback_logs`
Satu baris per **percobaan**, bukan per event. Retry membuat baris baru dengan
`event_id` sama dan `attempt` bertambah. Tanpa ini, menelusuri "kenapa project X
tidak menerima notifikasi" hampir mustahil.

### `audit_logs`
Polymorphic. Pelaku bisa admin (`user_id`) atau project lewat API
(`api_key_id`) — keduanya nullable dan tidak saling menggantikan.

## Sebelum menambah kolom

1. Apakah datanya durable, atau sebenarnya turunan yang bisa dihitung?
2. Kalau menyimpan nominal: sudah `bigint`?
3. Kalau menyimpan rahasia: perlu dibaca balik atau cukup dibandingkan?
4. Apakah perubahan ini memengaruhi index nominal aktif?
5. Buat migration baru, jangan edit migration yang sudah jalan di produksi.
