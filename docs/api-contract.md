# Kontrak API v1

Base path: `/api/v1`. Format request dan response: JSON.

Kontrak ini sudah dipakai project lain. **Perubahan yang memutus kompatibilitas
harus lewat `/api/v2`, bukan mengubah v1.**

## Autentikasi

Setiap request wajib membawa header berikut:

| Header | Isi |
|---|---|
| `X-Api-Key` | API key penuh milik project |
| `X-Timestamp` | Unix timestamp detik |
| `X-Signature` | HMAC-SHA256 hex, lihat di bawah |
| `Idempotency-Key` | Opsional, UUID dari project |

String yang ditandatangani:

```
METHOD \n PATH \n TIMESTAMP \n sha256_hex(RAW_BODY)
```

```
X-Signature = hex(hmac_sha256(signing_secret, string_to_sign))
```

Aturan verifikasi di server:

1. Ambil `key_prefix` dari `X-Api-Key`, cari `api_keys` lewat index.
2. Verifikasi `key_hash` dengan `hash_equals`, bukan `==`.
3. Tolak kalau `revoked_at` terisi atau `expires_at` sudah lewat.
4. Tolak kalau selisih `X-Timestamp` dengan waktu server lebih dari 300 detik
   (anti replay).
5. Hitung ulang HMAC dari **raw body**, bukan dari array hasil decode.
6. Bandingkan dengan `hash_equals`.

## Idempotency

Pasangan `(project_id, external_id)` unik di database. Kalau project mengirim
`external_id` yang sudah ada:

- kalau nominal dan project sama → kembalikan invoice yang sudah ada, HTTP 200;
- kalau berbeda → HTTP 409 dengan kode `external_id_conflict`.

Jangan pernah membuat invoice kedua untuk `external_id` yang sama.

## Endpoint

```
POST   /api/v1/invoices                      buat invoice
GET    /api/v1/invoices/{invoice_id}         detail invoice
GET    /api/v1/invoices/{invoice_id}/status  status ringkas (untuk polling)
POST   /api/v1/invoices/{invoice_id}/cancel  batalkan invoice
GET    /api/v1/transactions                  daftar invoice project
```

`confirm` dan `expire` **tidak** tersedia untuk project. Konfirmasi hanya dari
dashboard admin; expiry dijalankan sistem.

### POST /api/v1/invoices/{invoice_id}/cancel

Tidak ada body. Hanya bisa membatalkan invoice yang masih `waiting_payment`
(state machine yang sama dengan `docs/architecture.md` — invoice yang sudah
`manual_verification`, `paid`, `expired`, atau `cancelled` ditolak). Kalau
`expires_at` invoice sudah lewat saat cancel dipanggil, sistem meng-expire-nya
dulu (sama seperti `GET` detail/status), lalu tetap menolak cancel-nya dengan
`invoice_not_cancellable`.

Response `200` berbentuk sama dengan response detail invoice, `status` sudah
`cancelled`. Callback `invoice.cancelled` dikirim ke `callback_url` seperti
event lain.

Error khusus: `invoice_not_cancellable` (HTTP 409) kalau status invoice tidak
boleh berpindah ke `cancelled`.

### GET /api/v1/transactions

Daftar invoice milik project pemanggil, terbaru dulu. Endpoint ini **satu-
satunya** di v1 yang responsnya dibungkus `data`/`links`/`meta` ala pagination
Laravel — endpoint resource tunggal lain di atas sengaja tidak dibungkus.

Query param (semua opsional):

| Param | Isi |
|---|---|
| `status` | Filter exact match salah satu nilai `InvoiceStatus` |
| `external_id` | Filter exact match |
| `per_page` | 1–100, default 20 |
| `page` | Standar pagination Laravel |

```json
{
  "data": [
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
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 20, "total": 1 }
}
```

### POST /api/v1/invoices

```json
{
  "external_id": "ORDER-20260806-001",
  "amount": 100000,
  "description": "Pembayaran pesanan restoran",
  "customer_name": "Miftah",
  "callback_url": "https://restaurant.example.com/payment/callback",
  "metadata": { "table": "A3" }
}
```

`project_id` tidak dikirim di body — diambil dari API key. Ini mencegah satu
project membuat invoice atas nama project lain.

Response `201`:

```json
{
  "invoice_id": "INV-20260806-0001",
  "external_id": "ORDER-20260806-001",
  "base_amount": 100000,
  "unique_code": 237,
  "payment_amount": 100237,
  "status": "waiting_payment",
  "payment_url": "https://pay.example.com/invoice/INV-20260806-0001",
  "qr_image_url": "https://pay.example.com/qris/INV-20260806-0001.svg",
  "expires_at": "2026-08-06T15:30:00+07:00"
}
```

`qris_payload` mentah **tidak pernah** dikembalikan lewat API.

## Format error

```json
{
  "error": {
    "code": "invoice_not_found",
    "message": "Invoice tidak ditemukan.",
    "details": {}
  }
}
```

Kode yang sudah dipakai: `invalid_signature`, `invalid_timestamp`,
`api_key_revoked`, `project_inactive`, `external_id_conflict`,
`invoice_not_found`, `invoice_not_cancellable`, `no_available_amount`,
`validation_failed`, `rate_limit_exceeded`.

`no_available_amount` muncul saat rentang kode unik habis. Ini kondisi nyata,
bukan bug — HTTP 503, dan project sebaiknya retry.

## Callback

Dikirim saat status invoice berubah menjadi `paid`, `expired`, atau `cancelled`.

Header:

| Header | Isi |
|---|---|
| `X-Qris-Event` | `invoice.paid`, `invoice.expired`, `invoice.cancelled` |
| `X-Qris-Event-Id` | UUID, sama untuk semua percobaan retry |
| `X-Qris-Timestamp` | Unix timestamp |
| `X-Qris-Signature` | `hex(hmac_sha256(callback_signing_secret, timestamp . "." . raw_body))` |

`callback_signing_secret` disimpan per project (`projects.callback_signing_secret`),
terpisah dari `signing_secret` milik API key — satu project bisa punya
beberapa API key (termasuk yang sudah di-revoke), jadi tidak ada satu
`signing_secret` API key yang bisa dijadikan acuan tunggal untuk menandatangani
callback keluar.

Body memuat field yang sama dengan response detail invoice.

Aturan:

- Callback dianggap berhasil hanya kalau status response 2xx.
- Retry: percobaan pertama langsung, lalu jeda 10 detik, 60 detik, 5 menit,
  30 menit, 2 jam sebelum percobaan ke-2 sampai ke-6. Setelah percobaan ke-6
  gagal, invoice ditandai butuh perhatian admin di dashboard
  (`invoices.callback_failed_at` terisi).
- Setiap percobaan menulis satu baris `callback_logs`.
- Penerima harus idempotent lewat `X-Qris-Event-Id`. Sebutkan ini di dokumentasi
  integrasi untuk project.
- Timeout koneksi 10 detik. Jangan menunggu lebih lama; itu menyandera worker.

## Rate limit

Per API key: 60 request per menit untuk endpoint tulis, 300 per menit untuk
endpoint baca. Lampaui → HTTP 429 dengan header `Retry-After`.
