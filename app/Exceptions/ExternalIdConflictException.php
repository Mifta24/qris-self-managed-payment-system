<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ExternalIdConflictException extends RuntimeException
{
    public static function forExternalId(string $externalId): self
    {
        return new self(
            "external_id \"{$externalId}\" sudah dipakai invoice lain dengan nominal berbeda."
        );
    }
}
