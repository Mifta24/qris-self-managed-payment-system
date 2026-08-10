<?php

declare(strict_types=1);

use App\Support\QrCodeRenderer;
use Tests\Support\QrisTestPayload;

it('renders a payload into an svg document', function () {
    $svg = (new QrCodeRenderer)->render(QrisTestPayload::static());

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('</svg>');
});

it('is deterministic for the same payload', function () {
    $renderer = new QrCodeRenderer;
    $payload = QrisTestPayload::static();

    expect($renderer->render($payload))->toBe($renderer->render($payload));
});

it('renders different svgs for different payloads', function () {
    $renderer = new QrCodeRenderer;

    $first = $renderer->render(QrisTestPayload::static());
    $second = $renderer->render(QrisTestPayload::static(extraNodes: [QrisTestPayload::tlv('54', '1000')]));

    expect($first)->not->toBe($second);
});
