<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Menghasilkan signing_secret — secret terpisah dari API key, dipakai
 * project untuk menghitung X-Signature (lihat docs/api-contract.md).
 * Disimpan encrypted, bukan hashed, karena server perlu membacanya balik
 * untuk menghitung ulang HMAC saat verifikasi.
 */
final class SigningSecretGenerator
{
    private const LENGTH = 64;

    public function generate(): string
    {
        return Str::random(self::LENGTH);
    }
}
