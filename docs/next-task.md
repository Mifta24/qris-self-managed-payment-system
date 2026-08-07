---
description: Tentukan dan kerjakan tugas berikutnya dari roadmap
---

Baca `docs/roadmap.md` dan tentukan tugas belum selesai paling awal yang
dependensinya sudah terpenuhi.

Sebelum menulis kode:

1. Sebutkan tugas yang kamu pilih dan alasannya.
2. Baca dokumen rujukan yang relevan menurut tabel di `CLAUDE.md`.
3. Tulis rencana singkat: file apa yang dibuat/diubah, dan test apa yang akan
   membuktikan tugas ini selesai.
4. Berhenti dan tunggu persetujuan.

Setelah disetujui: tulis test lebih dulu kalau tugas ini menyentuh nominal atau
status invoice, baru implementasi. Jalankan `php artisan test` dan
`./vendor/bin/pint` sebelum melapor selesai.

Terakhir, centang item yang selesai di `docs/roadmap.md`.
