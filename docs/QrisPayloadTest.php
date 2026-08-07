<?php

declare(strict_types=1);

use App\Support\Qris\Exceptions\InvalidQrisPayloadException;
use App\Support\Qris\QrisPayload;
use Tests\Support\QrisTestPayload;

it('parses and reserialises a payload without changing it', function () {
    $raw = QrisTestPayload::static();

    expect(QrisPayload::parse($raw)->toString())->toBe($raw);
});

it('reads top level fields', function () {
    $payload = QrisPayload::parse(QrisTestPayload::static());

    expect($payload->merchantName())->toBe('TOKO MIFTAH')
        ->and($payload->merchantCity())->toBe('JAKARTA')
        ->and($payload->currency())->toBe('360')
        ->and($payload->pointOfInitiation())->toBe('11')
        ->and($payload->amount())->toBeNull();
});

it('parses nested template tags', function () {
    $payload = QrisPayload::parse(QrisTestPayload::static());

    $merchantAccount = $payload->get('26');

    expect($merchantAccount?->hasChildren())->toBeTrue()
        ->and($merchantAccount?->child('00')?->value())->toBe('ID.CO.QRIS.WWW')
        ->and($merchantAccount?->child('01')?->value())->toBe('936000091234567890');
});

it('validates the CRC of the source payload', function () {
    expect(QrisPayload::parse(QrisTestPayload::static())->crcIsValid())->toBeTrue();
});

it('detects a tampered payload', function () {
    $tampered = str_replace('TOKO MIFTAH', 'TOKO ORANGX', QrisTestPayload::static());

    expect(QrisPayload::parse($tampered)->crcIsValid())->toBeFalse();
});

it('inserts a new tag in numeric order', function () {
    $payload = QrisPayload::parse(QrisTestPayload::static());
    $payload->set(QrisPayload::TAG_AMOUNT, '100237');

    $result = $payload->toString();

    // Tag 54 harus muncul setelah 53 (currency) dan sebelum 58 (country).
    expect(strpos($result, '5303360'))->toBeLessThan(strpos($result, '5406100237'))
        ->and(strpos($result, '5406100237'))->toBeLessThan(strpos($result, '5802ID'));
});

it('replaces an existing tag in place instead of duplicating it', function () {
    $payload = QrisPayload::parse(QrisTestPayload::static());
    $payload->set(QrisPayload::TAG_AMOUNT, '1000');
    $payload->set(QrisPayload::TAG_AMOUNT, '2000');

    $result = $payload->toString();

    expect(substr_count($result, '54042000'))->toBe(1)
        ->and(str_contains($result, '1000'))->toBeFalse();
});

it('recomputes the CRC on every serialisation', function () {
    $payload = QrisPayload::parse(QrisTestPayload::static());
    $before = $payload->toString();

    $payload->set(QrisPayload::TAG_AMOUNT, '50000');
    $after = $payload->toString();

    expect(substr($before, -4))->not->toBe(substr($after, -4))
        ->and(QrisPayload::parse($after)->crcIsValid())->toBeTrue();
});

it('removes a tag', function () {
    $payload = QrisPayload::parse(QrisTestPayload::static([QrisTestPayload::tlv('55', '01')]));

    expect($payload->has('55'))->toBeTrue();

    $payload->remove('55');

    expect($payload->has('55'))->toBeFalse()
        ->and(QrisPayload::parse($payload->toString())->has('55'))->toBeFalse();
});

it('rejects malformed input', function (string $raw) {
    QrisPayload::parse($raw);
})->with([
    'kosong' => '',
    'bukan tlv' => 'not-a-qris-payload',
    'terpotong' => '00020101021126640014ID.CO.QRIS',
    'panjang melebihi sisa string' => '0099AB',
])->throws(InvalidQrisPayloadException::class);

it('returns null instead of throwing when parsing leniently', function () {
    expect(QrisPayload::tryParse('rusak'))->toBeNull()
        ->and(QrisPayload::tryParse(QrisTestPayload::static()))->toBeInstanceOf(QrisPayload::class);
});
