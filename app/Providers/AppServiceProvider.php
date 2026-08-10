<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // docs/api-contract.md mendokumentasikan response API v1 tanpa
        // wrapper "data" — matikan wrapping bawaan JsonResource supaya
        // bentuknya cocok persis.
        JsonResource::withoutWrapping();

        // Per API key, sesuai docs/api-contract.md. Laravel menjadwalkan
        // ThrottleRequests untuk jalan SEBELUM middleware custom seperti
        // VerifyApiSignature (lihat $middlewarePriority bawaan framework),
        // jadi request attributes hasil autentikasi belum tersedia di sini.
        // Dikunci dari key_prefix mentah di header X-Api-Key saja — cukup
        // stabil per kredensial tanpa perlu query database di jalur ini.
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(60)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(300)->by($this->rateLimitKey($request));
        });
    }

    private function rateLimitKey(Request $request): string
    {
        $apiKeyHeader = (string) $request->header('X-Api-Key');

        return $apiKeyHeader !== '' ? 'api-key:'.substr($apiKeyHeader, 0, 24) : $request->ip();
    }
}
