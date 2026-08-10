<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Bentuk error konsisten untuk API v1. Lihat format di docs/api-contract.md.
 */
final class ApiError
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function make(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
