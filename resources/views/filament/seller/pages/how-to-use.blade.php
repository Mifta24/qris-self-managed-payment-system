<x-filament-panels::page>
    <x-filament::section heading="Cara mengonfirmasi uang yang masuk">
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Sistem ini <strong>tidak tahu sendiri</strong> kalau uang sudah masuk. Anda yang memeriksa aplikasi
            GoPay Merchant, lalu memberi tahu sistem. Setelah itu pesanan pelanggan ditandai lunas.
        </p>

        <ol class="list-decimal space-y-3 pl-5 text-sm">
            <li>
                Buka menu <strong>Pembayaran</strong>. Tab <strong>Menunggu dicek</strong> berisi semua pembayaran
                yang belum Anda konfirmasi.
            </li>
            <li>
                Buka aplikasi <strong>GoPay Merchant</strong>, lalu lihat riwayat transaksi yang masuk.
            </li>
            <li>
                Cari transaksi yang <strong>jumlahnya sama persis</strong> dengan yang tertulis di kartu pembayaran,
                termasuk angka terakhirnya (kode unik). Contoh: kartu menulis <em>Rp 100.123</em>, maka yang dicari
                transaksi <em>Rp 100.123</em>, bukan <em>Rp 100.000</em>.
            </li>
            <li>
                Kalau sudah ketemu, tekan <strong>Uang sudah masuk</strong> di kartu itu.
            </li>
            <li>
                Isi jumlah uang (ketik ulang persis seperti di GoPay Merchant) dan <strong>nomor referensi</strong>.
                Nomor referensi ada di detail transaksi GoPay Merchant, biasanya tertulis
                <em>Nomor referensi</em> atau <em>RRN</em>. Salin dan tempel supaya tidak salah ketik.
            </li>
            <li>
                Tekan <strong>Ya, uang sudah masuk</strong>. Pembayaran pindah ke tab <strong>Sudah dibayar</strong>.
            </li>
        </ol>
    </x-filament::section>

    <x-filament::section heading="Kalau uangnya belum ada di GoPay Merchant">
        <p class="text-sm">
            <strong>Jangan tekan "Uang sudah masuk".</strong> Tunggu sampai transaksinya benar-benar tampil di
            GoPay Merchant. Konfirmasi yang sudah ditekan tidak bisa dibatalkan.
        </p>
    </x-filament::section>

    <x-filament::section heading="Tab Kedaluwarsa">
        <p class="text-sm">
            Pembayaran punya batas waktu. Kalau pelanggan membayar tepat setelah batas waktu lewat, uangnya tetap
            masuk. Cari pembayarannya di tab <strong>Kedaluwarsa</strong>, lalu konfirmasi seperti biasa. Anda wajib
            menulis catatan singkat, misalnya "pelanggan bayar 1 menit setelah batas waktu".
        </p>
    </x-filament::section>

    <x-filament::section heading="Pesan: jumlah tidak cocok">
        <p class="text-sm">
            Sistem menolak konfirmasi kalau jumlah yang Anda ketik berbeda dari jumlah pada kartu. Ini disengaja
            supaya Anda tidak salah menandai pesanan pelanggan lain sebagai lunas. Periksa lagi jumlah di GoPay
            Merchant, terutama angka terakhirnya.
        </p>
    </x-filament::section>
</x-filament-panels::page>
