<?php

declare(strict_types=1);

use App\Support\SigningSecretGenerator;

it('generates a 64 character secret', function () {
    expect(strlen((new SigningSecretGenerator)->generate()))->toBe(64);
});

it('generates a different secret on every call', function () {
    $generator = new SigningSecretGenerator;

    expect($generator->generate())->not->toBe($generator->generate());
});
