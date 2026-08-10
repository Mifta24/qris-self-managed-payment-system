<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Payload string -> SVG mentah. Fungsi murni: payload yang sama selalu
 * menghasilkan SVG yang sama, jadi hasilnya aman di-cache berdasarkan hash
 * payload (lihat pemanggilnya di QrImageController).
 */
final class QrCodeRenderer
{
    private const SIZE_PX = 320;

    public function render(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::SIZE_PX, margin: 1),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($payload);
    }
}
