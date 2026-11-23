<?php

declare(strict_types=1);

use App\Filament\Resources\SellerAccounts\Pages\EditSellerAccount;
use App\Filament\Resources\SellerAccounts\Pages\ListSellerAccounts;
use App\Filament\Seller\Pages\Auth\RegisterSeller;
use App\Filament\Seller\Pages\Auth\SellerLogin;
use App\Models\QrisMerchant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\QrisTestPayload;

function registrationData(array $override = []): array
{
    return array_merge([
        'name' => 'Bu Sari',
        'email' => 'sari@example.test',
        'registration_note' => 'Warung Sari, WA 081234567890',
        'password' => 'rahasia-panjang-123',
        'passwordConfirmation' => 'rahasia-panjang-123',
    ], $override);
}

function makeShopFor(string $code = 'warung-sari'): QrisMerchant
{
    return QrisMerchant::create([
        'code' => $code, 'label' => 'Toko '.$code, 'static_payload' => QrisTestPayload::static(), 'currency' => '360',
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('seller'));
});

it('shows the registration page to a guest', function () {
    $this->get('/toko/register')->assertSuccessful()->assertSee('Daftar');
});

it('registers a pending seller who is not an admin, has no shop and is not logged in', function () {
    Livewire::test(RegisterSeller::class)
        ->fillForm(registrationData())
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getLoginUrl());

    $user = User::where('email', 'sari@example.test')->firstOrFail();

    expect($user->is_admin)->toBeFalse()
        ->and($user->qrisMerchants()->count())->toBe(0)
        ->and($user->registration_note)->toBe('Warung Sari, WA 081234567890')
        ->and(Hash::check('rahasia-panjang-123', $user->password))->toBeTrue()
        ->and($user->canAccessPanel(Filament::getPanel('seller')))->toBeFalse();

    $this->assertGuest();
});

it('cannot register as admin even when is_admin is sent along', function () {
    Livewire::test(RegisterSeller::class)
        ->set('data.is_admin', true)
        ->fillForm(registrationData())
        ->call('register');

    expect(User::where('email', 'sari@example.test')->firstOrFail()->is_admin)->toBeFalse();
});

it('requires the shop note, a long enough password and a unique email', function () {
    User::factory()->create(['email' => 'sari@example.test']);

    Livewire::test(RegisterSeller::class)
        ->fillForm(registrationData(['registration_note' => '', 'password' => 'pendek', 'passwordConfirmation' => 'pendek']))
        ->call('register')
        ->assertHasFormErrors(['registration_note' => 'required', 'password', 'email' => 'unique']);
});

it('notifies admins, and only admins, about a new registration', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    Livewire::test(RegisterSeller::class)->fillForm(registrationData())->call('register');

    expect($admin->notifications()->count())->toBe(1)
        ->and($other->notifications()->count())->toBe(0);
});

it('tells a pending seller that the account is awaiting approval instead of a generic error', function () {
    User::create(['name' => 'Bu Sari', 'email' => 'sari@example.test', 'password' => 'rahasia-panjang-123']);

    Livewire::test(SellerLogin::class)
        ->fillForm(['email' => 'sari@example.test', 'password' => 'rahasia-panjang-123'])
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    $this->assertGuest();
});

it('keeps a wrong password on the generic error', function () {
    User::create(['name' => 'Bu Sari', 'email' => 'sari@example.test', 'password' => 'rahasia-panjang-123']);

    $component = Livewire::test(SellerLogin::class)
        ->fillForm(['email' => 'sari@example.test', 'password' => 'salah-total-123'])
        ->call('authenticate');

    expect(collect($component->errors()->get('data.email'))->join(' '))->not->toContain('disetujui');
});

it('lets the seller log in once an admin links a shop', function () {
    $pending = User::create(['name' => 'Bu Sari', 'email' => 'sari@example.test', 'password' => 'rahasia-panjang-123']);
    $shop = makeShopFor();

    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditSellerAccount::class, ['record' => $pending->getKey()])
        ->fillForm(['qrisMerchants' => [$shop->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pending->fresh()->canAccessPanel(Filament::getPanel('seller')))->toBeTrue();
});

it('flags pending accounts for the admin and lets the admin reject them', function () {
    $pending = User::create(['name' => 'Spam', 'email' => 'spam@example.test', 'password' => 'rahasia-panjang-123']);
    $active = User::factory()->create();
    $active->qrisMerchants()->attach(makeShopFor());

    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListSellerAccounts::class)
        ->assertCanSeeTableRecords([$pending, $active])
        ->filterTable('pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$active]);

    Livewire::test(EditSellerAccount::class, ['record' => $pending->getKey()])
        ->callAction('reject');

    expect(User::whereKey($pending->id)->exists())->toBeFalse();
});

it('does not offer to delete a seller who is already active', function () {
    $active = User::factory()->create();
    $active->qrisMerchants()->attach(makeShopFor());

    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditSellerAccount::class, ['record' => $active->getKey()])
        ->assertActionHidden('reject');
});
