---
description: Review perubahan dari sisi keamanan uang dan integritas data
---

Review perubahan yang belum di-commit (`git diff`) khusus dari sudut pandang
integritas uang. Ini bukan review gaya kode.

Periksa satu per satu dan laporkan temuan beserta lokasi barisnya:

1. **Tipe data nominal** — ada `float`, `decimal`, atau perhitungan nominal di
   JavaScript?
2. **Race condition** — ada pola `SELECT` untuk mengecek ketersediaan lalu
   `INSERT`? Ada operasi multi-langkah yang menyentuh nominal tapi di luar
   `DB::transaction`?
3. **Transisi status** — ada perubahan status invoice yang tidak lewat state
   machine? Ada jalur yang bisa mengubah invoice `paid`?
4. **Kunci nominal** — ada perubahan yang melonggarkan atau melewati unique
   index pada nominal invoice aktif?
5. **Kebocoran rahasia** — payload QRIS, API key, atau signing secret masuk ke
   log, response API, pesan exception, atau halaman publik?
6. **Signature** — perbandingan pakai `hash_equals`, bukan `==` atau `===`?
   HMAC dihitung dari raw body, bukan hasil decode?
7. **Idempotency** — ada jalur yang bisa menghasilkan dua invoice untuk satu
   `external_id`, atau memproses satu callback dua kali?
8. **Audit** — setiap perubahan status menulis audit log?

Kalau tidak ada temuan pada suatu poin, cukup tulis satu baris bahwa poin itu
aman. Jangan mengarang temuan untuk terlihat teliti.
