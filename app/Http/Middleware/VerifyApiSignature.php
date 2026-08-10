<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi API key + HMAC sesuai docs/api-contract.md. Urutan verifikasi
 * mengikuti urutan yang didokumentasikan di sana — jangan diacak, beberapa
 * langkah sengaja gagal cepat sebelum menyentuh signing_secret.
 */
final class VerifyApiSignature
{
    private const MAX_TIMESTAMP_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $apiKeyHeader = (string) $request->header('X-Api-Key');
        $timestampHeader = (string) $request->header('X-Timestamp');
        $signatureHeader = (string) $request->header('X-Signature');

        if ($apiKeyHeader === '' || $signatureHeader === '') {
            return ApiError::make('invalid_signature', 'Signature tidak valid.', 401);
        }

        if ($timestampHeader === '' || ! ctype_digit($timestampHeader)) {
            return ApiError::make('invalid_timestamp', 'Timestamp tidak valid.', 401);
        }

        $apiKey = ApiKey::where('key_prefix', substr($apiKeyHeader, 0, 24))->first();

        if ($apiKey === null || ! hash_equals($apiKey->key_hash, hash('sha256', $apiKeyHeader))) {
            return ApiError::make('invalid_signature', 'Signature tidak valid.', 401);
        }

        if ($apiKey->revoked_at !== null || $apiKey->expires_at?->isPast() === true) {
            return ApiError::make('api_key_revoked', 'API key sudah dicabut atau kedaluwarsa.', 401);
        }

        if (abs(now()->getTimestamp() - (int) $timestampHeader) > self::MAX_TIMESTAMP_SKEW_SECONDS) {
            return ApiError::make('invalid_timestamp', 'Timestamp kedaluwarsa.', 401);
        }

        $expectedSignature = hash_hmac('sha256', $this->stringToSign($request, $timestampHeader), $apiKey->signing_secret);

        if (! hash_equals($expectedSignature, $signatureHeader)) {
            return ApiError::make('invalid_signature', 'Signature tidak valid.', 401);
        }

        $project = $apiKey->project;

        if (! $project->is_active) {
            return ApiError::make('project_inactive', 'Project tidak aktif.', 403);
        }

        $apiKey->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->save();

        $request->attributes->set('project', $project);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    private function stringToSign(Request $request, string $timestampHeader): string
    {
        return implode("\n", [
            $request->method(),
            $request->getPathInfo(),
            $timestampHeader,
            hash('sha256', $request->getContent()),
        ]);
    }
}
