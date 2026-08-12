<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiRequestSigner;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Mengirim request JSON yang ditandatangani penuh (X-Api-Key/X-Timestamp/
 * X-Signature) sesuai skema di docs/api-contract.md. Body di-encode sekali
 * lalu dipakai persis sama untuk penandatanganan dan pengiriman, supaya
 * tidak ada celah perbedaan encoding antara keduanya.
 *
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $headerOverrides
 */
function callSigned(
    string $method,
    string $uri,
    array $payload,
    string $apiKeyPlain,
    string $signingSecret,
    array $headerOverrides = [],
    ?int $timestamp = null,
): TestResponse {
    $body = $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR);

    // Signature dihitung dari path saja, sama seperti VerifyApiSignature
    // yang memakai getPathInfo() (tanpa query string).
    $path = strtok($uri, '?');

    $headers = ApiRequestSigner::headers($apiKeyPlain, $signingSecret, $method, $path, $body, $timestamp);

    $headers = array_merge($headers, [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ], $headerOverrides);

    return test()->call($method, $uri, [], [], [], test()->transformHeadersToServerVars($headers), $body);
}
