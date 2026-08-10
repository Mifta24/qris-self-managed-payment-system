<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Menghasilkan API key: prefix untuk lookup satu query, hash untuk
 * autentikasi. Lihat catatan pemisahan key_prefix/key_hash/signing_secret
 * di docs/database.md.
 *
 * key_hash sengaja sha256 (deterministik), bukan bcrypt — verifikasi di
 * docs/api-contract.md memakai hash_equals atas key_hash, yang cuma valid
 * untuk hash yang reproducible dari input yang sama.
 */
final class ApiKeyGenerator
{
    private const PREFIX_LENGTH = 24;

    /**
     * @return array{key: string, prefix: string, hash: string}
     */
    public function generate(): array
    {
        $key = 'qris_live_'.Str::random(32);

        return [
            'key' => $key,
            'prefix' => substr($key, 0, self::PREFIX_LENGTH),
            'hash' => hash('sha256', $key),
        ];
    }
}
