# Panduan Penggunaan

Dokumen ini untuk **pemakai sistem**: admin yang mengoperasikan dashboard, dan
developer di project lain yang mengintegrasikan API. Untuk detail kontrak
teknis API, lihat `docs/api-contract.md`. Untuk alur sistem, lihat
`docs/architecture.md`. Dokumen ini merangkum keduanya jadi langkah praktis.

Endpoint aktif saat ini: **create, get detail, get status, cancel, dan list
transactions** — semuanya sudah bisa diintegrasikan project lain. `confirm`
dan `expire` tetap tidak tersedia lewat API, sesuai desain sistem (lihat §1).

---

## 1. Setup awal oleh admin (dashboard)

Login ke dashboard Filament, lalu urutan setup untuk project baru:

### 1.1 Tambah QRIS Merchant

Menu **QRIS Merchants** → New.

1. Isi `code` (slug internal, mis. `gopay-utama`) dan `label`.
2. Isi `Payload QRIS statis` — bisa ditempel manual atau pakai fitur **Scan
   dari foto QRIS** (unggah/ambil foto stiker QRIS, payload terisi otomatis).
3. Setelah payload valid, field `merchant_name`, `merchant_city`, `currency`
   terisi otomatis dari payload (tag 59/60/53). `national_merchant_id`
   (NMID) harus diisi manual dari stiker fisik kalau ada.
4. Simpan. Payload disimpan terenkripsi dan **tidak pernah ditampilkan balik**
   di form — untuk mengganti, isi ulang field payload.
5. Isi `verified_at` setelah payload benar-benar diuji scan di aplikasi
   pembayar sungguhan.

### 1.2 Tambah Project

Menu **Projects** → New.

| Field | Isi dengan |
|---|---|
| `slug` | ID unik project, dipakai sebagai `project_id` konseptual. Tidak bisa diubah setelah dibuat. |
| `name` | Nama tampilan di dashboard saja. |
| `QRIS Merchant` | Merchant yang dibuat di langkah 1.1. |
| `callback_url` | URL endpoint di sistem project lain yang akan menerima notifikasi perubahan status invoice. Kosongkan kalau project tidak butuh callback. |
| `callback_signing_secret` | Digenerate otomatis, dipakai project untuk verifikasi signature callback masuk. **Catat sekarang**, tampil lagi di halaman edit (field ini `copyable`). |
| `invoice_ttl_minutes` | Default 30. Lama invoice boleh menunggu sebelum `expired`. |
| `unique_code_min` / `unique_code_max` | Default 1–999. Rentang kode unik yang ditambahkan ke nominal dasar. Menentukan berapa banyak invoice aktif dengan nominal sama yang bisa dibedakan sekaligus. |
| `is_active` | Nonaktifkan untuk menolak semua request API project ini tanpa menghapus datanya. |

### 1.3 Buat API Key

Menu **API Keys** → **Buat API Key**.

1. Pilih `project`, isi `name` (label bebas, mis. nama environment).
2. Opsional: `expires_at`, `allowed_ips` (catatan: pembatasan IP tersimpan
   tapi **belum aktif difilter** di server saat ini).
3. Setelah disimpan, muncul notifikasi berisi `X-Api-Key` dan `Signing secret`
   **satu kali saja**. Salin dan simpan ke secret manager project lain — tidak
   bisa dilihat ulang dari dashboard. Kalau hilang, revoke key ini dan buat
   yang baru.

Sampai di sini, project lain sudah bisa mulai memanggil API.

---

## 2. Integrasi dari project lain (pemanggil API)

### 2.1 Yang dibutuhkan dari admin

- `X-Api-Key` (full key, hanya muncul sekali saat dibuat)
- `signing_secret` (untuk menandatangani request keluar)
- `callback_signing_secret` milik project (untuk memverifikasi callback masuk,
  beda dari `signing_secret` di atas)
- Base URL sistem ini

### 2.2 Menyusun signature request

Setiap request ke `/api/v1/*` wajib membawa header:

| Header | Isi |
|---|---|
| `X-Api-Key` | API key penuh |
| `X-Timestamp` | Unix timestamp detik (server tolak kalau selisih > 300 detik) |
| `X-Signature` | HMAC-SHA256 hex dari string di bawah |
| `Content-Type` | `application/json` |

String yang ditandatangani (gabung dengan `\n`):

```
METHOD
PATH
TIMESTAMP
sha256_hex(RAW_BODY)
```

`PATH` adalah path saja, tanpa host/query string, contoh `/api/v1/invoices`.
`RAW_BODY` untuk request `GET` tanpa body adalah string kosong.

```
X-Signature = hex( HMAC_SHA256( signing_secret, string_to_sign ) )
```

Contoh Node.js:

```js
const crypto = require('crypto');

function signRequest(method, path, rawBody, signingSecret) {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const bodyHash = crypto.createHash('sha256').update(rawBody).digest('hex');
  const stringToSign = [method, path, timestamp, bodyHash].join('\n');
  const signature = crypto
    .createHmac('sha256', signingSecret)
    .update(stringToSign)
    .digest('hex');

  return {
    'X-Api-Key': process.env.QRIS_API_KEY,
    'X-Timestamp': timestamp,
    'X-Signature': signature,
    'Content-Type': 'application/json',
  };
}
```

Contoh PHP:

```php
function signRequest(string $method, string $path, string $rawBody, string $signingSecret): array
{
    $timestamp = (string) time();
    $bodyHash = hash('sha256', $rawBody);
    $stringToSign = implode("\n", [$method, $path, $timestamp, $bodyHash]);
    $signature = hash_hmac('sha256', $stringToSign, $signingSecret);

    return [
        'X-Api-Key' => config('services.qris.api_key'),
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'Content-Type' => 'application/json',
    ];
}
```

Penting: hitung `X-Signature` dari **raw body string** yang benar-benar
dikirim, sebelum di-encode ulang. Kalau library HTTP kamu meng-encode ulang
JSON secara berbeda dari yang kamu hash, signature akan selalu gagal.

### 2.3 Membuat invoice

```
POST /api/v1/invoices
```

Body:

```json
{
  "external_id": "ORDER-20260812-001",
  "amount": 100000,
  "description": "Pembayaran pesanan",
  "customer_name": "Miftah",
  "customer_email": "miftah@example.com",
  "customer_phone": "081234567890",
  "callback_url": "https://project-lain.example.com/webhook/qris",
  "metadata": { "table": "A3" }
}
```

Field wajib: `external_id` (string, unik per project, dipakai sebagai kunci
idempotency), `amount` (integer rupiah, minimal 1). Field lain opsional.

`project_id` **tidak dikirim di body** — sistem mengambilnya dari API key
yang dipakai, supaya satu project tidak bisa membuat invoice atas nama
project lain.

Response `201` (invoice baru) atau `200` (external_id sudah pernah dipakai
dengan data sama — idempotent, invoice lama dikembalikan):

```json
{
  "invoice_id": "INV-20260812-0001",
  "external_id": "ORDER-20260812-001",
  "base_amount": 100000,
  "unique_code": 237,
  "payment_amount": 100237,
  "status": "waiting_payment",
  "payment_url": "https://pay.example.com/invoice/INV-20260812-0001",
  "qr_image_url": "https://pay.example.com/qris/INV-20260812-0001.svg",
  "expires_at": "2026-08-12T15:30:00+07:00"
}
```

Arahkan customer ke `payment_url` (halaman pembayaran publik, sudah ada QR,
countdown, dan auto-polling status). `qris_payload` mentah **tidak pernah**
dikirim lewat API — kalau perlu menampilkan QR sendiri, embed `qr_image_url`,
jangan coba merekonstruksi payload.

Kalau `external_id` yang sama dikirim ulang dengan `amount` atau project
berbeda dari invoice yang sudah ada → `409 external_id_conflict`. Jangan retry
begitu saja; itu tandanya ada bug di sisi pengirim (mis. ID order dipakai
ulang).

### 2.4 Mengecek status

Dua cara, pilih sesuai kebutuhan:

```
GET /api/v1/invoices/{invoice_id}          -> detail lengkap (bentuk sama dengan response create)
GET /api/v1/invoices/{invoice_id}/status   -> ringkas, buat polling
```

Response `status`:

```json
{
  "invoice_id": "INV-20260812-0001",
  "status": "waiting_payment",
  "expires_at": "2026-08-12T15:30:00+07:00",
  "paid_at": null
}
```

Nilai `status` yang mungkin: `created`, `waiting_payment`,
`manual_verification`, `paid`, `expired`, `cancelled`, `failed`. Detail
transisi ada di `docs/architecture.md`.

Sistem **tidak** menyediakan endpoint untuk mengubah invoice jadi `paid` lewat
API — itu murni tindakan manual admin di dashboard setelah mengecek mutasi
GoPay Merchant. Jangan bangun asumsi bahwa project bisa "menandai lunas
sendiri".

### 2.5 Membatalkan invoice

```
POST /api/v1/invoices/{invoice_id}/cancel
```

Tidak ada body. Hanya bisa membatalkan invoice yang masih `waiting_payment`.
Kalau `expires_at` invoice sudah lewat saat dipanggil, sistem meng-expire-nya
dulu (bukan membatalkan) — jadi tetap dapat error, bukan sukses.

Response `200` bentuknya sama dengan detail invoice, `status` jadi
`cancelled`, dan callback `invoice.cancelled` dikirim kalau `callback_url`
diisi. Error khusus: `invoice_not_cancellable` (409) kalau status invoice
tidak boleh berpindah ke `cancelled` — misalnya sudah `paid` atau sudah
dibatalkan sebelumnya.

### 2.6 Melihat daftar invoice (transactions)

```
GET /api/v1/transactions?status=waiting_payment&per_page=20&page=1
```

Semua query param opsional: `status` (salah satu nilai `InvoiceStatus`),
`external_id` (exact match), `per_page` (1–100, default 20), `page`.

Beda dengan endpoint lain, response endpoint ini **dibungkus**
`data`/`links`/`meta` ala pagination Laravel — bukan objek invoice langsung:

```json
{
  "data": [ { "invoice_id": "INV-20260812-0001", "...": "..." } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 20, "total": 1 }
}
```

### 2.7 Menerima callback

Kalau `callback_url` diisi (saat buat project di dashboard, atau per invoice
lewat field `callback_url` di body create), sistem akan mengirim `POST` ke
URL itu saat status invoice berubah jadi `paid`, `expired`, atau `cancelled`.

Header yang dikirim:

| Header | Isi |
|---|---|
| `X-Qris-Event` | `invoice.paid` / `invoice.expired` / `invoice.cancelled` |
| `X-Qris-Event-Id` | UUID, sama di semua percobaan retry event yang sama |
| `X-Qris-Timestamp` | Unix timestamp saat callback dikirim |
| `X-Qris-Signature` | `hex(hmac_sha256(callback_signing_secret, timestamp . "." . raw_body))` |

Verifikasi di sisi penerima (wajib, jangan skip):

```php
$expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $callbackSigningSecret);
if (! hash_equals($expected, $request->header('X-Qris-Signature'))) {
    abort(401);
}
```

Body callback berisi field yang sama dengan response detail invoice.

Aturan penting untuk penerima:

- **Balas HTTP 2xx** kalau sudah berhasil diproses. Selain 2xx dianggap gagal
  dan akan di-retry (interval: langsung, 10 detik, 60 detik, 5 menit, 30
  menit, 2 jam — 6 percobaan total).
- **Idempotent berdasarkan `X-Qris-Event-Id`.** Callback yang sama bisa datang
  lebih dari sekali (retry, atau replay manual dari admin). Jangan proses dua
  kali kalau `X-Qris-Event-Id` sudah pernah diterima.
- Jangan proses lama — timeout koneksi dari pengirim 10 detik.

### 2.8 Format error

Semua error API v1 berbentuk:

```json
{
  "error": {
    "code": "invoice_not_found",
    "message": "Invoice tidak ditemukan.",
    "details": {}
  }
}
```

| Kode | HTTP | Kapan muncul | Yang harus dilakukan pemanggil |
|---|---|---|---|
| `invalid_signature` | 401 | `X-Api-Key`/`X-Signature` kosong atau salah | Cek ulang cara hitung signature, jangan retry naif |
| `invalid_timestamp` | 401 | `X-Timestamp` bukan angka atau selisih > 300 detik | Sinkronkan jam server pengirim |
| `api_key_revoked` | 401 | Key sudah dicabut/kedaluwarsa | Minta key baru ke admin |
| `project_inactive` | 403 | Project dinonaktifkan admin | Hubungi admin |
| `external_id_conflict` | 409 | `external_id` dipakai ulang dengan data beda | Perbaiki logika ID order di sisi pengirim |
| `invoice_not_cancellable` | 409 | Cancel dipanggil pada invoice yang bukan `waiting_payment` | Cek status dulu lewat GET sebelum cancel, jangan retry naif |
| `no_available_amount` | 503 | Rentang kode unik habis untuk merchant ini | Retry dengan backoff — ini kondisi nyata, bukan bug |
| `validation_failed` | 422 | Body tidak lolos validasi | Cek `details` untuk field yang salah |
| `invoice_not_found` | 404 | Invoice tidak ada / bukan milik project ini | — |
| `rate_limit_exceeded` | 429 | Lampaui rate limit | Hormati header `Retry-After` |

### 2.9 Rate limit

Per API key: **60 req/menit** untuk endpoint tulis (`POST /invoices`), **300
req/menit** untuk endpoint baca (`GET`). Lampaui → `429` + header
`Retry-After`.

---

## 3. Operasional harian oleh admin (dashboard)

### 3.1 Mengonfirmasi pembayaran

Menu **Invoices**, cari invoice berstatus menunggu (bisa difilter per status
dan per project).

1. Buka aplikasi GoPay Merchant, lihat mutasi masuk.
2. Cari invoice dengan `payment_amount` yang cocok persis dengan nominal
   mutasi (nominal dasar + kode unik).
3. Klik **Konfirmasi**, isi:
   - `Nominal yang diterima` — harus cocok dengan `payment_amount` invoice,
     sistem menolak kalau beda.
   - `RRN / nomor referensi` — dari detail mutasi GoPay Merchant.
   - `Waktu bayar` — waktu transaksi menurut GoPay Merchant, bukan waktu
     admin menekan tombol (dua hal ini dicatat terpisah untuk rekonsiliasi).
   - `Catatan` — opsional.
4. Submit. Status berubah jadi `paid`, audit log tertulis otomatis, dan kalau
   project punya `callback_url`, callback `invoice.paid` masuk antrian kirim.

Jangan pernah mengonfirmasi invoice dengan nominal yang tidak cocok persis —
itu menghapus manfaat kode unik dan bisa salah menandai invoice orang lain
sebagai lunas.

### 3.2 Invoice yang sudah expired tapi ternyata dibayar

Ini kasus nyata (customer bayar beberapa detik setelah expired). Admin **boleh**
mengubah `expired` → `paid`, tapi wajib mengisi alasan, dan sistem akan
menolak kalau nominal itu ternyata sudah dipakai invoice aktif lain sejak
kode uniknya dilepas. Kalau ditolak, selesaikan manual (lihat
`docs/architecture.md` bagian "Pembayaran terlambat").

### 3.3 Callback yang gagal total

Filter/badge di tabel invoice menandai invoice yang `callback_failed_at`
terisi — artinya 6 percobaan callback ke project sudah habis. Admin perlu
menghubungi tim project itu secara manual (callback tidak di-retry otomatis
lagi setelah percobaan ke-6).

### 3.4 Widget ringkasan

Dashboard menampilkan widget statistik invoice (jumlah per status, dll) di
halaman utama Filament untuk pengecekan cepat tanpa buka tabel penuh.

---

## 4. Halaman pembayaran publik (untuk customer)

Tidak perlu autentikasi apa pun — link `payment_url` dari response API sudah
cukup untuk dibagikan ke customer.

```
GET /invoice/{invoice_number}          halaman pembayaran (QR, nominal, countdown)
GET /invoice/{invoice_number}/status   dipanggil halaman itu sendiri untuk auto-polling
GET /qris/{invoice_number}.svg         gambar QR (SVG, di-generate on the fly, dicache)
```

Halaman otomatis polling status tiap beberapa detik dan menampilkan tampilan
berbeda untuk invoice yang sudah `paid`, `expired`, atau `cancelled`.

---

## 5. Checklist integrasi cepat (project baru)

- [ ] Minta admin buat QRIS Merchant (kalau merchant belum ada) dan Project
- [ ] Terima `X-Api-Key`, `signing_secret`, `callback_signing_secret` dari admin
      lewat jalur aman (bukan chat biasa)
- [ ] Implementasi signing HMAC untuk request keluar (lihat §2.2)
- [ ] Implementasi verifikasi HMAC untuk callback masuk (lihat §2.5)
- [ ] Panggil `POST /api/v1/invoices` dengan `external_id` unik per order
- [ ] Redirect/tampilkan `payment_url` ke customer
- [ ] Endpoint callback: verifikasi signature, balas 2xx cepat, idempotent
      berdasarkan `X-Qris-Event-Id`
- [ ] Tangani `no_available_amount` (503) dengan retry, bukan gagal permanen
- [ ] Kalau perlu batalkan order dari sisi project, panggil `cancel` — jangan
      cuma berhenti memproses tanpa memberi tahu sistem
