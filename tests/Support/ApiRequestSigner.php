<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Membangun header X-Api-Key/X-Timestamp/X-Signature untuk test, mengikuti
 * persis skema penandatanganan di docs/api-contract.md.
 */
final class ApiRequestSigner
{
    /**
     * @return array<string, string>
     */
    public static function headers(
        string $apiKey,
        string $signingSecret,
        string $method,
        string $path,
        string $rawBody,
        ?int $timestamp = null,
    ): array {
        $timestamp ??= now()->getTimestamp();

        $stringToSign = implode("\n", [
            $method,
            $path,
            (string) $timestamp,
            hash('sha256', $rawBody),
        ]);

        return [
            'X-Api-Key' => $apiKey,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => hash_hmac('sha256', $stringToSign, $signingSecret),
        ];
    }
}
