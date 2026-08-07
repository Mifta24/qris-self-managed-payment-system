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

## Fase 2 — API bisa dipakai project

- [ ] Middleware autentikasi API key + HMAC (lihat `docs/api-contract.md`)
- [ ] `POST /invoices`, `GET /invoices/{id}`, `GET /invoices/{id}/status`
- [ ] Rate limiting per API key
- [ ] Format error konsisten
- [ ] Test: signature salah, timestamp kedaluwarsa, key dicabut, replay

## Fase 3 — Customer bisa membayar

- [ ] Renderer QR: payload → SVG, dicache berdasarkan hash payload
- [ ] Halaman pembayaran publik: QR, nominal, countdown, instruksi
- [ ] Polling status tiap 5 detik
- [ ] Halaman untuk invoice expired / sudah dibayar / dibatalkan

## Fase 4 — Admin bisa mengonfirmasi

- [ ] Filament: resource Project, ApiKey, QrisMerchant, Invoice
- [ ] Antrian verifikasi: daftar invoice menunggu, urut nominal
- [ ] Action konfirmasi: input RRN + waktu bayar, validasi nominal cocok
- [ ] Audit log tercatat di setiap perubahan status
- [ ] Notifikasi Telegram saat invoice baru dibuat

## Fase 5 — Sistem bisa ditinggal jalan

- [ ] Scheduler expiry + expiry lazy saat invoice dibaca
- [ ] `SendCallbackJob` dengan signature dan retry berjenjang
- [ ] `callback_logs` per percobaan
- [ ] Dashboard: invoice yang callback-nya gagal total
- [ ] Uji scan di 6 aplikasi pembayar (lihat `docs/qris-spec.md`)

## Belum akan dikerjakan

Provider adapter, refund, settlement tracking, rekonsiliasi otomatis, laporan
keuangan. Semua ini menunggu akses QRIS API resmi. Jangan bangun abstraksinya
sekarang — cukup jangan menutup jalannya dengan kontrak API yang terlalu terikat
ke GoPay.
