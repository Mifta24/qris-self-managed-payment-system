<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\QrCodeRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Satu-satunya jalan payload QRIS invoice sampai ke customer: dirender jadi
 * SVG di server, tidak pernah sebagai string mentah. Lihat aturan mutlak #5
 * di CLAUDE.md.
 */
final class QrImageController extends Controller
{
    public function __construct(
        private readonly QrCodeRenderer $renderer,
    ) {}

    public function show(Invoice $invoice): Response
    {
        $svg = Cache::rememberForever(
            'qr-svg:'.hash('sha256', $invoice->qris_payload),
            fn (): string => $this->renderer->render($invoice->qris_payload),
        );

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
