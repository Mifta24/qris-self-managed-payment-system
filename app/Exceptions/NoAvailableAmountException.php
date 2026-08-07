<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class NoAvailableAmountException extends RuntimeException
{
    public static function rangeExhausted(int $projectId, int $min, int $max): self
    {
        return new self(
            "Rentang kode unik project #{$projectId} ({$min}-{$max}) sudah habis. ".
            'Semua nominal di rentang ini sedang dipakai invoice aktif lain.'
        );
    }
}
