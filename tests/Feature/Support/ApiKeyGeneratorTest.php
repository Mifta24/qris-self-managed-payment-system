<?php

declare(strict_types=1);

use App\Support\ApiKeyGenerator;

it('generates a key whose prefix is the first 24 characters of the full key', function () {
    $result = (new ApiKeyGenerator)->generate();

    expect($result['key'])->toStartWith('qris_live_')
        ->and(strlen($result['key']))->toBe(42)
        ->and($result['prefix'])->toBe(substr($result['key'], 0, 24))
        ->and(strlen($result['prefix']))->toBe(24);
});

it('hashes the full key deterministically with sha256 so it can be verified with hash_equals', function () {
    $result = (new ApiKeyGenerator)->generate();

    expect($result['hash'])->toBe(hash('sha256', $result['key']));
});

it('generates a different key on every call', function () {
    $generator = new ApiKeyGenerator;

    expect($generator->generate()['key'])->not->toBe($generator->generate()['key']);
});
