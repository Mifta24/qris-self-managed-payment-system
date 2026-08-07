<?php

declare(strict_types=1);

namespace App\Support\Qris\Exceptions;

use RuntimeException;

final class InvalidQrisPayloadException extends RuntimeException
{
    public static function empty(): self
    {
        return new self('Payload QRIS kosong.');
    }

    public static function malformedAt(int $offset): self
    {
        return new self("Struktur TLV tidak valid pada posisi {$offset}.");
    }

    public static function truncated(int $offset): self
    {
        return new self("Payload QRIS terpotong pada posisi {$offset}.");
    }

    public static function invalidCrc(): self
    {
        return new self('CRC payload QRIS tidak cocok. Payload kemungkinan salah salin atau rusak.');
    }

    public static function valueTooLong(string $tag, int $length): self
    {
        return new self("Nilai tag {$tag} melebihi 99 karakter ({$length}).");
    }

    public static function invalidAmount(int $amount): self
    {
        return new self("Nominal transaksi harus lebih besar dari nol, diterima: {$amount}.");
    }

    public static function amountTooLarge(int $amount): self
    {
        return new self("Nominal transaksi melebihi batas 13 digit: {$amount}.");
    }

    public static function amountAlreadyPresent(): self
    {
        return new self('Payload sudah memuat nominal (tag 54). Gunakan QRIS statis tanpa nominal.');
    }

    public static function unsupportedCurrency(string $currency): self
    {
        return new self("Mata uang {$currency} tidak didukung. Sistem hanya menerima 360 (IDR).");
    }
}
