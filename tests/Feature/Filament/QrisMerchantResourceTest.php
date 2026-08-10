<?php

declare(strict_types=1);

use App\Filament\Resources\QrisMerchants\Pages\CreateQrisMerchant;
use App\Filament\Resources\QrisMerchants\Pages\EditQrisMerchant;
use App\Filament\Resources\QrisMerchants\Pages\ListQrisMerchants;
use App\Models\QrisMerchant;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates a qris merchant with a static payload', function () {
    Livewire::test(CreateQrisMerchant::class)
        ->fillForm([
            'code' => 'gopay-utama',
            'label' => 'GoPay Merchant',
            'static_payload' => QrisTestPayload::static(),
            'currency' => '360',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $merchant = QrisMerchant::where('code', 'gopay-utama')->first();

    expect($merchant)->not->toBeNull()
        ->and($merchant->static_payload)->toBe(QrisTestPayload::static());
});

it('never shows the existing static_payload back in the edit form', function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    Livewire::test(EditQrisMerchant::class, ['record' => $merchant->getRouteKey()])
        ->assertFormSet(['static_payload' => null]);
});

it('keeps the existing static_payload when the field is left blank on update', function () {
    $originalPayload = QrisTestPayload::static();

    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => $originalPayload,
        'currency' => '360',
    ]);

    Livewire::test(EditQrisMerchant::class, ['record' => $merchant->getRouteKey()])
        ->fillForm(['label' => 'GoPay Merchant Baru', 'static_payload' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($merchant->fresh())
        ->label->toBe('GoPay Merchant Baru')
        ->static_payload->toBe($originalPayload);
});

it('replaces the static_payload when a new value is submitted on update', function () {
    $merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $newPayload = QrisTestPayload::static(extraNodes: [QrisTestPayload::tlv('62', '0000')]);

    Livewire::test(EditQrisMerchant::class, ['record' => $merchant->getRouteKey()])
        ->fillForm(['static_payload' => $newPayload])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($merchant->fresh()->static_payload)->toBe($newPayload);
});

it('never lists static_payload as a table column', function () {
    QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    Livewire::test(ListQrisMerchants::class)
        ->assertDontSee(QrisTestPayload::static());
});
