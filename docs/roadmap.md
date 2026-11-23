# Roadmap

Urutan ini disusun berdasarkan ketergantungan, bukan preferensi. Jangan lompat
fase kecuali ada alasan jelas.

## Fase 0 — Fondasi ✅

- [x] Skema database (6 migration)
- [x] Parser TLV + CRC16 + generator QRIS dinamis
- [x] Test untuk domain QRIS

## Fase 1 — Invoice bisa dibuat ✅

- [x] Enum `InvoiceStatus` dengan state machine
- [x] Model + cast (`encrypted`, `integer`, `datetime`) sesuai `docs/database.md`
- [x] `UniqueCodeAllocator` — alokasi lewat unique constraint, retry saat
      bentrok, `NoAvailableAmountException` saat rentang habis
- [x] `InvoiceService::create()` — satu transaksi: alokasi + generate + simpan
- [x] Test: bentrok kode unik, rentang habis, idempotency

## Fase 2 — API bisa dipakai project ✅

- [x] Middleware autentikasi API key + HMAC (lihat `docs/api-contract.md`)
- [x] `POST /invoices`, `GET /invoices/{id}`, `GET /invoices/{id}/status`
- [x] Rate limiting per API key
- [x] Format error konsisten
- [x] Test: signature salah, timestamp kedaluwarsa, key dicabut, replay

## Fase 3 — Customer bisa membayar ✅

- [x] Renderer QR: payload → SVG, dicache berdasarkan hash payload
- [x] Halaman pembayaran publik: QR, nominal, countdown, instruksi
- [x] Polling status tiap 5 detik
- [x] Halaman untuk invoice expired / sudah dibayar / dibatalkan

## Fase 4 — Admin bisa mengonfirmasi ✅

- [x] Filament: resource Project, ApiKey, QrisMerchant, Invoice
- [x] Antrian verifikasi: daftar invoice menunggu, urut nominal
- [x] Action konfirmasi: input RRN + waktu bayar, validasi nominal cocok
- [x] Audit log tercatat di setiap perubahan status
- [x] Notifikasi Telegram saat invoice baru dibuat

## Fase 5 — Sistem bisa ditinggal jalan

- [x] Scheduler expiry + expiry lazy saat invoice dibaca
- [x] `SendCallbackJob` dengan signature dan retry berjenjang
- [x] `callback_logs` per percobaan
- [x] Dashboard: invoice yang callback-nya gagal total
- [ ] Uji scan di 6 aplikasi pembayar (lihat `docs/qris-spec.md`)

## Fase 6 — Dashboard penjual UMKM

- [x] Kolom `users.is_admin` + pivot `qris_merchant_user`; akses panel per role
- [x] Panel `/toko` (tenancy per QrisMerchant), bahasa Indonesia
- [x] Beranda: uang masuk hari ini/bulan ini, menunggu dicek, uang masuk terakhir
- [x] Daftar pembayaran per tab + filter periode + total
- [x] Konfirmasi "Uang sudah masuk" lewat `InvoicePaymentConfirmationService`
- [x] Halaman Cara Pakai
- [x] Admin: menu Akun Penjual
- [ ] Notifikasi untuk penjual (Telegram/email saat ada pembayaran menunggu)
- [ ] Mode kasir: penjual membuat tagihan sendiri tanpa lewat API project

## Belum akan dikerjakan

Provider adapter, refund, settlement tracking, rekonsiliasi otomatis, laporan
keuangan. Semua ini menunggu akses QRIS API resmi. Jangan bangun abstraksinya
sekarang — cukup jangan menutup jalannya dengan kontrak API yang terlalu terikat
ke GoPay.
