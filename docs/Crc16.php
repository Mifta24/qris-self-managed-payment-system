<?php

declare(strict_types=1);

namespace App\Support\Qris;

/**
 * CRC-16/CCITT-FALSE — varian yang dipakai standar EMVCo QR Code.
 *
 * Parameter: poly 0x1021, init 0xFFFF, tanpa refleksi input/output,
 * tanpa XOR akhir. Check value resmi: "123456789" => 29B1.
 *
 * Jangan tergoda mengganti dengan CRC16 varian lain (XModem, ARC, dsb).
 * Semuanya memakai polinomial 0x1021 tapi nilai init berbeda, dan hasilnya
 * akan membuat QR ditolak oleh aplikasi pembayar tanpa pesan error yang jelas.
 */
final class Crc16
{
    private const POLYNOMIAL = 0x1021;
    private const INITIAL = 0xFFFF;

    /**
     * Menghitung CRC dan mengembalikannya sebagai 4 karakter hex uppercase.
     */
    public static function compute(string $data): string
    {
        $crc = self::INITIAL;

        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ self::POLYNOMIAL)
                    : ($crc << 1);

                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public static function matches(string $data, string $expected): bool
    {
        return hash_equals(self::compute($data), strtoupper($expected));
    }
}
