<?php

declare(strict_types=1);

use App\Support\Qris\Crc16;

it('matches the official CRC-16/CCITT-FALSE check value', function () {
    // Nilai referensi resmi. Kalau test ini merah, algoritmanya salah varian
    // dan seluruh QR yang dihasilkan akan ditolak aplikasi pembayar.
    expect(Crc16::compute('123456789'))->toBe('29B1');
});

it('returns the initial value for an empty string', function () {
    expect(Crc16::compute(''))->toBe('FFFF');
});

it('always returns four uppercase hex characters', function (string $input) {
    expect(Crc16::compute($input))->toMatch('/^[0-9A-F]{4}$/');
})->with(['a', 'QRIS', '00020101021126', str_repeat('x', 500)]);

it('compares case-insensitively', function () {
    $crc = Crc16::compute('QRIS');

    expect(Crc16::matches('QRIS', strtolower($crc)))->toBeTrue()
        ->and(Crc16::matches('QRIS', '0000'))->toBeFalse();
});
