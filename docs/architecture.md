# Arsitektur

## Modul

```
QRIS Payment System
├── API Layer          autentikasi API key + HMAC, rate limit, idempotency
├── Invoice            pembuatan, pembacaan, pembatalan, expiry
├── Unique Code        alokasi kode unik dengan kunci database
├── QRIS               parser TLV, CRC16, generator nominal   [SUDAH JADI]
├── QR Render          payload -> SVG, dicache, tidak disimpan sebagai file
├── Payment Page       halaman publik per invoice, polling status
├── Admin Dashboard    Filament: project, API key, antrian verifikasi, akun penjual
├── Seller Dashboard   Filament /toko: pembayaran toko sendiri, konfirmasi uang masuk
├── Verification       konfirmasi manual oleh admin
├── Notification       Telegram (utama), email, WhatsApp (opsional)
├── Callback           pengiriman event ke project + retry + log
└── Audit              catatan append-only untuk semua perubahan sensitif
```

## Alur pembuatan invoice

```
Project ──POST /api/v1/invoices──> API Layer
                                      │ verifikasi API key + HMAC
                                      │ cek idempotency (project_id, external_id)
                                      ▼
                                 InvoiceService
                                      │ DB::transaction
                                      │  ├── UniqueCodeAllocator: alokasi kode unik
                                      │  │   (INSERT, tangkap unique violation, retry)
                                      │  ├── DynamicQrisGenerator: payload + nominal
                                      │  └── simpan invoice (status: waiting_payment)
                                      ▼
                            dispatch NotifyAdminJob (queue)
                                      ▼
                            response: payment_url + qr_image_url
```

Poin penting: alokasi kode unik dan penyimpanan invoice berada dalam **satu**
transaksi. Kalau dipisah, ada jendela waktu di mana nominal belum terkunci.

## Alur konfirmasi pembayaran

```
Customer bayar via QRIS
        │
        ▼
Admin (atau penjual pemilik toko lewat /toko) buka aplikasi GoPay Merchant, lihat mutasi masuk
        │
        ▼
Admin cari invoice dengan payment_amount yang cocok di dashboard
        │
        ▼
Admin isi RRN + waktu bayar, tekan konfirmasi
        │
        ├── validasi: invoice masih boleh dikonfirmasi?
        ├── update status -> paid, isi paid_at & confirmed_at
        ├── tulis audit log
        └── dispatch SendCallbackJob (queue)
                │
                ▼
        Project menerima POST callback, verifikasi signature
```

Nominal yang diinput admin **wajib** dicocokkan dengan `payment_amount` invoice
oleh sistem. Jangan biarkan admin mengonfirmasi invoice dengan nominal yang
tidak sama — itu justru menghapus manfaat kode unik.

## Transisi status

| Dari | Ke | Pemicu |
|---|---|---|
| `created` | `waiting_payment` | invoice selesai dibuat dan QR siap |
| `created` | `failed` | generator gagal / alokasi kode habis |
| `waiting_payment` | `manual_verification` | admin mulai memeriksa |
| `waiting_payment` | `paid` | admin konfirmasi langsung |
| `waiting_payment` | `expired` | lewat `expires_at` |
| `waiting_payment` | `cancelled` | dibatalkan project atau admin |
| `manual_verification` | `paid` | data cocok |
| `manual_verification` | `waiting_payment` | data tidak cocok, dikembalikan |
| `expired` | `paid` | **pembayaran terlambat**, lihat catatan di bawah |
| `paid` | — | terminal |
| `cancelled` / `failed` | — | terminal |

Transisi selain di atas harus ditolak oleh state machine, bukan hanya oleh
validasi form.

### Pembayaran terlambat

Ini akan terjadi: customer scan QR menjelang expiry lalu membayar beberapa detik
setelah invoice kedaluwarsa. Uangnya sudah masuk, jadi menolak mentah-mentah
bukan pilihan.

Aturannya: admin boleh mengubah `expired` menjadi `paid`, tetapi:
- catatan alasan wajib diisi;
- audit log menandai transisi ini secara khusus;
- kode unik invoice tersebut sudah dilepas saat expired, jadi sistem harus
  memeriksa apakah nominal itu sudah dipakai invoice aktif lain. Kalau ya,
  konfirmasi ditolak dan harus diselesaikan manual.

## Batasan yang disengaja

- Tidak ada verifikasi otomatis. Bottleneck sistem ini adalah manusia.
- Notifikasi invoice baru **bukan** notifikasi pembayaran berhasil. Jangan
  pernah menulis teks notifikasi yang bisa disalahartikan sebagai konfirmasi.
- Jumlah invoice aktif per merchant dibatasi oleh rentang kode unik
  (default 1-999). Ini batas nyata sistem, bukan batas server.

## Jalur pengembangan

Kontrak API v1 sengaja dibuat menyerupai payment gateway sungguhan. Saat nanti
tersedia QRIS API resmi dari PJP, yang berubah hanya lapisan provider di
belakang; project yang sudah terintegrasi tidak perlu diubah. Jangan membangun
lapisan adapter itu sekarang — cukup jangan menutup jalannya.
