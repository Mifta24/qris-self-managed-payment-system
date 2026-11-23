<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel penjual selalu berbahasa Indonesia (label bawaan Filament, pesan
 * validasi, format tanggal), tanpa mengubah locale panel admin.
 */
final class UseIndonesianLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('id');

        return $next($request);
    }
}
