<?php

declare(strict_types=1);

use App\Filament\Resources\ApiKeys\Pages\EditApiKey;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Models\User;
use App\Support\ApiKeyGenerator;
use Livewire\Livewire;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $this->project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $merchant->id,
    ]);

    $this->actingAs(User::factory()->create());
});

it('creates an api key with server-generated secrets and notifies the plaintext values once', function () {
    Livewire::test(ListApiKeys::class)
        ->callAction('createApiKey', data: [
            'project_id' => $this->project->id,
            'name' => 'Kasir utama',
        ])
        ->assertNotified();

    $apiKey = ApiKey::where('project_id', $this->project->id)->first();

    expect($apiKey)->not->toBeNull()
        ->and($apiKey->key_prefix)->not->toBeEmpty()
        ->and($apiKey->key_hash)->not->toBeEmpty()
        ->and($apiKey->signing_secret)->not->toBeEmpty();
});

it('does not expose key_hash or signing_secret in the edit form', function () {
    $generated = (new ApiKeyGenerator)->generate();

    $apiKey = ApiKey::create([
        'project_id' => $this->project->id,
        'name' => 'Kasir utama',
        'key_prefix' => $generated['prefix'],
        'key_hash' => $generated['hash'],
        'signing_secret' => 'super-secret-signing-value',
    ]);

    Livewire::test(EditApiKey::class, ['record' => $apiKey->getRouteKey()])
        ->assertFormFieldDoesNotExist('key_hash')
        ->assertFormFieldDoesNotExist('signing_secret');
});

it('revokes an api key through the table action', function () {
    $generated = (new ApiKeyGenerator)->generate();

    $apiKey = ApiKey::create([
        'project_id' => $this->project->id,
        'name' => 'Kasir utama',
        'key_prefix' => $generated['prefix'],
        'key_hash' => $generated['hash'],
        'signing_secret' => 'super-secret-signing-value',
    ]);

    Livewire::test(ListApiKeys::class)
        ->callTableAction('revoke', $apiKey)
        ->assertNotified();

    expect($apiKey->fresh()->revoked_at)->not->toBeNull();
});
