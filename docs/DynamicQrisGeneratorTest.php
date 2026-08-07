<?php

declare(strict_types=1);

use App\Support\Qris\DynamicQrisGenerator;
use App\Support\Qris\Exceptions\InvalidQrisPayloadException;
use App\Support\Qris\QrisPayload;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $this->generator = new DynamicQrisGenerator();
});

it('adds the transaction amount and keeps the payload valid', function () {
    $result = $this->generator->generate(QrisTestPayload::static(), 100237);

    $parsed = QrisPayload::parse($result);

    expect($parsed->amount())->toBe('100237')
        ->and($parsed->crcIsValid())->toBeTrue()
        ->and($parsed->merchantName())->toBe('TOKO MIFTAH');
});

it('leaves the point of initiation untouched by default', function () {
    // Default sengaja tidak mengubah 11 -> 12. Lihat catatan di generator:
    // sebagian aplikasi bank menolak QR statis yang ditandai dinamis.
    $result = $this->generator->generate(QrisTestPayload::static(), 5000);

    expect(QrisPayload::parse($result)->pointOfInitiation())->toBe('11');
});

it('can mark the payload as dynamic when explicitly enabled', function () {
    $generator = new DynamicQrisGenerator(markAsDynamic: true);

    $result = $generator->generate(QrisTestPayload::static(), 5000);

    expect(QrisPayload::parse($result)->pointOfInitiation())->toBe('12');
});

it('strips tip fields so the payer app cannot add an extra amount', function () {
    $static = QrisTestPayload::static([
        QrisTestPayload::tlv('55', '01'),
        QrisTestPayload::tlv('56', '5000'),
    ]);

    $result = $this->generator->generate($static, 25000);
    $parsed = QrisPayload::parse($result);

    expect($parsed->has('55'))->toBeFalse()
        ->and($parsed->has('56'))->toBeFalse()
        ->and($parsed->amount())->toBe('25000');
});

it('keeps tip fields when stripping is disabled', function () {
    $generator = new DynamicQrisGenerator(stripTipFields: false);
    $static = QrisTestPayload::static([QrisTestPayload::tlv('55', '01')]);

    expect(QrisPayload::parse($generator->generate($static, 1000))->has('55'))->toBeTrue();
});

it('replaces the amount when regenerating an already generated payload', function () {
    $first = $this->generator->generate(QrisTestPayload::static(), 100237);
    $second = $this->generator->generate($first, 777);

    $parsed = QrisPayload::parse($second);

    expect($parsed->amount())->toBe('777')
        ->and(substr_count($second, '5403777'))->toBe(1)
        ->and($parsed->crcIsValid())->toBeTrue();
});

it('refuses a source payload with a broken CRC', function () {
    $tampered = str_replace('TOKO MIFTAH', 'TOKO ORANGX', QrisTestPayload::static());

    $this->generator->generate($tampered, 1000);
})->throws(InvalidQrisPayloadException::class);

it('refuses a non rupiah payload', function () {
    $static = QrisTestPayload::static();
    $usd = QrisTestPayload::withCrc(str_replace('5303360', '5303840', substr($static, 0, -8)));

    $this->generator->generate($usd, 1000);
})->throws(InvalidQrisPayloadException::class);

it('refuses an invalid amount', function (int $amount) {
    $this->generator->generate(QrisTestPayload::static(), $amount);
})->with([
    'nol' => 0,
    'negatif' => -5000,
    'lebih dari 13 digit' => 99999999999999,
])->throws(InvalidQrisPayloadException::class);

it('inspects a static payload before it is stored', function () {
    $info = $this->generator->inspect(QrisTestPayload::static());

    expect($info)->toBe([
        'merchant_name' => 'TOKO MIFTAH',
        'merchant_city' => 'JAKARTA',
        'currency' => '360',
    ]);
});

it('rejects a static payload that already carries an amount', function () {
    $withAmount = $this->generator->generate(QrisTestPayload::static(), 1000);

    $this->generator->inspect($withAmount);
})->throws(InvalidQrisPayloadException::class);

/**
 * Golden test.
 *
 * Isi QRIS_TEST_STATIC_PAYLOAD di phpunit.xml atau .env.testing dengan payload
 * GoPay Merchant asli, lalu jalankan sekali. Kalau CRC payload asli sudah tidak
 * valid, biasanya penyebabnya salah salin dari hasil scan — bukan bug parser.
 */
it('parses the real merchant payload', function () {
    $raw = env('QRIS_TEST_STATIC_PAYLOAD');

    if (blank($raw)) {
        $this->markTestSkipped('QRIS_TEST_STATIC_PAYLOAD belum diisi.');
    }

    $payload = QrisPayload::parse($raw);

    expect($payload->crcIsValid())->toBeTrue()
        ->and($payload->toString())->toBe(trim($raw))
        ->and($payload->currency())->toBe('360')
        ->and($payload->amount())->toBeNull();

    $result = (new DynamicQrisGenerator())->generate($raw, 100237);

    expect(QrisPayload::parse($result)->crcIsValid())->toBeTrue();
});
