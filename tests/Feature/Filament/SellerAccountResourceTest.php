<?php

declare(strict_types=1);

use App\Filament\Resources\SellerAccounts\Pages\CreateSellerAccount;
use App\Filament\Resources\SellerAccounts\Pages\ListSellerAccounts;
use App\Models\QrisMerchant;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->merchant = QrisMerchant::create([
        'code' => 'toko-a', 'label' => 'Toko A', 'static_payload' => QrisTestPayload::static(), 'currency' => '360',
    ]);
    $this->actingAs($this->admin);
});

it('creates a seller account linked to a shop and never as admin', function () {
    Livewire::test(CreateSellerAccount::class)
        ->fillForm([
            'name' => 'Bu Sari',
            'email' => 'sari@example.test',
            'password' => 'rahasia-panjang-123',
            'qrisMerchants' => [$this->merchant->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'sari@example.test')->firstOrFail();

    expect($user->is_admin)->toBeFalse()
        ->and($user->password)->not->toBe('rahasia-panjang-123')
        ->and($user->qrisMerchants->pluck('id')->all())->toBe([$this->merchant->id]);
});

it('requires at least one shop', function () {
    Livewire::test(CreateSellerAccount::class)
        ->fillForm([
            'name' => 'Bu Sari',
            'email' => 'sari@example.test',
            'password' => 'rahasia-panjang-123',
            'qrisMerchants' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['qrisMerchants' => 'required']);
});

it('lists seller accounts but not admins', function () {
    $seller = User::factory()->create();
    $seller->qrisMerchants()->attach($this->merchant);

    Livewire::test(ListSellerAccounts::class)
        ->assertCanSeeTableRecords([$seller])
        ->assertCanNotSeeTableRecords([$this->admin]);
});
