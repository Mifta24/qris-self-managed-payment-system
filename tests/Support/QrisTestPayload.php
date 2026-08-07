<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Qris\Crc16;

/**
 * Builder payload QRIS statis untuk keperluan test.
 *
 * CRC dihitung memakai Crc16 milik aplikasi, bukan angka hardcoded. Ini
 * disengaja: test di sini menguji parser dan generator, sedangkan kebenaran
 * algoritma CRC diuji terpisah lewat check value resmi "123456789" => 29B1
 * di Crc16Test. Kalau CRC di-hardcode di sini, satu bug akan menyembunyikan
 * bug yang lain.
 */
final class QrisTestPayload
{
    public static function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /**
     * @param  array<int, string>  $extraNodes  TLV mentah yang disisipkan sebelum tag 58.
     */
    public static function static(array $extraNodes = []): string
    {
        $merchantAccount = self::tlv('00', 'ID.CO.QRIS.WWW')
            .self::tlv('01', '936000091234567890')
            .self::tlv('02', '1234567890123')
            .self::tlv('03', 'UMI');

        $body = self::tlv('00', '01')
            .self::tlv('01', '11')
            .self::tlv('26', $merchantAccount)
            .self::tlv('52', '5812')
            .self::tlv('53', '360')
            .implode('', $extraNodes)
            .self::tlv('58', 'ID')
            .self::tlv('59', 'TOKO MIFTAH')
            .self::tlv('60', 'JAKARTA')
            .self::tlv('61', '12345');

        return self::withCrc($body);
    }

    public static function withCrc(string $body): string
    {
        return $body.'6304'.Crc16::compute($body.'6304');
    }
}
