# Konvensi kode

## Dasar

- `declare(strict_types=1);` di setiap file PHP.
- Kelas `final` secara default. Buka hanya kalau memang diwarisi.
- Property `readonly` untuk dependency dan value object.
- Constructor property promotion.
- Return type dan parameter type selalu eksplisit, termasuk `void`.
- Formatter: `./vendor/bin/pint` sebelum commit.

## Struktur

| Jenis | Lokasi | Tugas |
|---|---|---|
| Controller | `app/Http/Controllers/Api` | terima request, panggil service, kembalikan resource. Tanpa logika bisnis. |
| Form Request | `app/Http/Requests` | validasi input |
| Service | `app/Services` | logika bisnis, orkestrasi transaksi |
| Value object / helper murni | `app/Support` | tanpa dependency Laravel kalau bisa |
| Job | `app/Jobs` | pekerjaan async (notifikasi, callback) |
| Enum | `app/Enums` | status invoice, jenis event |

Controller yang lebih dari ~30 baris biasanya tanda logika bocor ke tempat yang
salah.

## Status invoice

Pakai backed enum, bukan string mentah:

```php
enum InvoiceStatus: string
{
    case Created = 'created';
    case WaitingPayment = 'waiting_payment';
    case ManualVerification = 'manual_verification';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function canTransitionTo(self $target): bool { /* ... */ }
    public function isTerminal(): bool { /* ... */ }
    public function locksAmount(): bool { /* ... */ }
}
```

Transisi status hanya lewat method di service yang memanggil
`canTransitionTo()`. Jangan pernah `$invoice->update(['status' => 'paid'])`
langsung dari controller atau Filament action.

## Transaksi database

Semua yang menyentuh nominal atau status dibungkus `DB::transaction`. Untuk
alokasi kode unik: `INSERT` lalu tangkap `QueryException` unique violation, lalu
retry. **Jangan** `SELECT` untuk mengecek ketersediaan lebih dulu — itu race
condition.

## Exception

- Domain exception sendiri, bukan `\Exception` generik.
- Pesan exception yang bisa dilihat admin: bahasa Indonesia, jelas, tanpa jargon.
- Jangan pernah memasukkan payload QRIS, API key, atau signing secret ke pesan
  exception atau log.

## Test

Wajib ada test untuk:

- alokasi kode unik, termasuk skenario bentrok dan rentang habis;
- setiap transisi status yang sah, dan penolakan transisi yang tidak sah;
- verifikasi signature HMAC, termasuk timestamp kedaluwarsa dan signature salah;
- idempotency `external_id`;
- apa pun yang menghitung atau membandingkan nominal.

Untuk bug: tulis test yang gagal lebih dulu, baru perbaiki.

Gaya Pest, deskripsi test dalam bahasa Inggris (`it('rejects an expired
timestamp')`), dataset boleh berlabel Indonesia.

## Logging

- Log setiap kegagalan callback beserta status code dan response body.
- Log setiap penolakan autentikasi API beserta `key_prefix` — **tidak pernah**
  API key penuh.
- Jangan log payload QRIS, `signing_secret`, atau isi `static_payload`.

## Commit

Bahasa Inggris, imperative, satu maksud per commit.

```
add unique code allocator with database-level locking
fix crc recalculation when tip fields are stripped
```
