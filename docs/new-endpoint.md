---
description: Tambahkan endpoint API v1 baru sesuai kontrak
argument-hint: [method dan path, contoh: POST /invoices/{id}/cancel]
---

Tambahkan endpoint: $ARGUMENTS

Baca `docs/api-contract.md` lebih dulu. Endpoint baru harus konsisten dengan
yang sudah ada — jangan menciptakan pola baru.

Wajib ada:

1. Form Request untuk validasi input.
2. Controller tipis: validasi → service → API Resource. Tanpa logika bisnis.
3. Logika di service, bukan di controller.
4. Autentikasi API key + HMAC lewat middleware yang sudah ada.
5. Rate limit sesuai jenis endpoint (tulis atau baca).
6. Error memakai format dan kode yang sudah terdaftar. Kalau butuh kode baru,
   tambahkan ke daftar di `docs/api-contract.md`.
7. Test: jalur sukses, input tidak valid, signature salah, dan otorisasi
   (project tidak boleh menyentuh invoice milik project lain).

Yang tidak boleh:

- Mengembalikan `qris_payload` mentah.
- Menerima `project_id` dari body — selalu ambil dari API key.
- Mengubah bentuk response endpoint v1 yang sudah ada.
- Menyediakan jalur bagi project untuk menandai invoice sebagai `paid`.

Setelah selesai, perbarui daftar endpoint di `docs/api-contract.md`.
