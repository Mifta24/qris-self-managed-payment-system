# Spesifikasi QRIS untuk repo ini

Implementasi ada di `app/Support/Qris/`. Dokumen ini menjelaskan alasannya.

## Format TLV

Payload QRIS adalah rangkaian TLV (Tag-Length-Value):

```
[2 digit tag][2 digit panjang desimal][value]
```

Karena panjang hanya 2 digit desimal, satu value maksimal 99 karakter.

Sebagian tag berisi TLV lagi (template): **26-51** (merchant account
information), **62** (additional data), **64** (merchant information language),
**80-99** (unreserved). Parser mengurai isinya jadi anak TLV. Kalau isi template
ternyata bukan TLV valid — implementasi acquirer tidak selalu patuh — node itu
diperlakukan sebagai value mentah, bukan alasan menolak seluruh payload.

## Tag yang dipakai sistem ini

| Tag | Isi | Catatan |
|---|---|---|
| `00` | Payload format indicator | selalu `01` |
| `01` | Point of Initiation Method | `11` statis, `12` dinamis |
| `26`-`51` | Merchant account information | template bersarang |
| `52` | Merchant category code | |
| `53` | Transaction currency | `360` = IDR, sistem menolak selain ini |
| `54` | **Transaction amount** | yang disisipkan generator |
| `55` | Tip indicator | dibuang generator |
| `56` | Fixed tip amount | dibuang generator |
| `57` | Percentage tip | dibuang generator |
| `58` | Country code | `ID` |
| `59` | Merchant name | |
| `60` | Merchant city | |
| `61` | Postal code | |
| `62` | Additional data | template bersarang |
| `63` | **CRC** | selalu terakhir, panjang selalu `04` |

## CRC

Varian: **CRC-16/CCITT-FALSE**. Polinomial `0x1021`, init `0xFFFF`, tanpa
refleksi input/output, tanpa XOR akhir. Check value resmi:
`"123456789"` → `29B1`, diuji di `Crc16Test`.

CRC dihitung atas seluruh payload **termasuk** string `6304` (tag dan panjang
CRC itu sendiri), tetapi tidak termasuk 4 karakter nilai CRC-nya.

Varian CRC16 lain (XModem, ARC, MODBUS) juga memakai polinomial `0x1021` atau
mirip tetapi dengan init berbeda. Salah pilih varian menghasilkan QR yang
ditolak aplikasi pembayar **tanpa pesan error yang jelas** — gejalanya hanya
"QR tidak dikenali". Kalau ada laporan seperti itu, cek CRC lebih dulu.

## Aturan modifikasi payload

1. **Payload sumber wajib lolos cek CRC** sebelum dimodifikasi. Kalau sumbernya
   rusak (biasanya salah salin dari hasil scan), hasilnya pasti salah.
2. **Tag 54 disisipkan pada posisi terurut numerik**, yaitu setelah `53` dan
   sebelum `58`. EMVCo tidak mewajibkan urutan, tetapi sebagian parser di sisi
   aplikasi bank mengasumsikannya.
3. **Tag 01 dibiarkan `11` secara default.** Secara standar, QR bernominal
   seharusnya `12`, tetapi sebagian aplikasi bank memvalidasi bahwa QR dinamis
   berasal dari PJP dan menolak QR statis yang ditandai dinamis. Mode dinamis
   dibuat opt-in: `new DynamicQrisGenerator(markAsDynamic: true)`.
4. **Tag tip (55/56/57) dibuang.** Kalau dibiarkan, sebagian aplikasi tetap
   meminta pengguna menambah nominal — dan itu merusak seluruh premis
   pencocokan lewat kode unik.
5. **CRC selalu dihitung ulang** di `QrisPayload::toString()`. Tag 63 tidak
   pernah diserialisasi sebagai node biasa, sehingga mustahil menghasilkan
   payload dengan CRC basi.
6. **Nominal maksimal 13 digit**, harus lebih besar dari nol, tanpa pemisah
   ribuan, tanpa desimal untuk IDR.

## Uji scan sebelum produksi

Payload hasil generate wajib diuji scan di, minimal: **Gojek, DANA, OVO,
ShopeePay, BCA mobile, Livin by Mandiri**. Perilaku antar aplikasi berbeda,
terutama soal tag 01.

Isi `QRIS_TEST_STATIC_PAYLOAD` di `.env.testing` dengan payload asli agar golden
test di `DynamicQrisGeneratorTest` ikut berjalan.

## Batasan yang perlu disadari

Memodifikasi payload QRIS yang diterbitkan acquirer untuk menambahkan nominal
berada di luar ketentuan merchant agreement pada umumnya, dan standar QRIS
mengatur bahwa QR dinamis dihasilkan oleh PJP. Praktik ini lazim untuk kebutuhan
internal, tetapi bukan jalur resmi. Untuk transaksi bernilai besar atau
penggunaan lintas banyak project, jalur yang benar adalah QRIS dinamis dari PJP
berizin — yang sekaligus memberi webhook dan menghapus kebutuhan verifikasi
manual.
