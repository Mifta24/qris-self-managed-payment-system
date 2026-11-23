<?php

declare(strict_types=1);

use App\Models\QrisMerchant;
use App\Models\User;
use Tests\Support\QrisTestPayload;

function makeMerchant(string $code): QrisMerchant
{
    return QrisMerchant::create([
        'code' => $code,
        'label' => 'Toko '.$code,
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);
}

it('redirects a guest away from the admin panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('redirects a guest away from the seller panel', function () {
    $this->get('/toko')->assertRedirect('/toko/login');
});

it('allows an admin to access the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSuccessful();
});

it('forbids a seller from the admin panel', function () {
    $seller = User::factory()->create();
    $seller->qrisMerchants()->attach(makeMerchant('toko-a'));

    $this->actingAs($seller)->get('/admin')->assertForbidden();
});

it('forbids a user without any merchant from both panels', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get('/toko')->assertForbidden();
});

it('forbids an admin who is not linked to a merchant from the seller panel', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/toko')->assertForbidden();
});

it('lets a seller open the dashboard of their own shop', function () {
    $merchant = makeMerchant('toko-a');
    $seller = User::factory()->create();
    $seller->qrisMerchants()->attach($merchant);

    $this->actingAs($seller)->get('/toko/toko-a')->assertSuccessful();
});

it('forbids a seller from opening the dashboard of another shop', function () {
    $mine = makeMerchant('toko-a');
    makeMerchant('toko-b');
    $seller = User::factory()->create();
    $seller->qrisMerchants()->attach($mine);

    $this->actingAs($seller)->get('/toko/toko-b')->assertNotFound();
});

it('cannot mass assign is_admin', function () {
    $user = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-pass', 'is_admin' => true]);

    expect($user->fresh()->is_admin)->toBeFalse();
});

it('renders the how-to page for a seller', function () {
    $seller = User::factory()->create();
    $seller->qrisMerchants()->attach(makeMerchant('toko-a'));

    $this->actingAs($seller)->get('/toko/toko-a/cara-pakai')
        ->assertSuccessful()
        ->assertSee('Cara mengonfirmasi uang yang masuk');
});

it('renders the seller account list for an admin and hides it from a seller', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/seller-accounts')->assertSuccessful();

    $seller = User::factory()->create();
    $seller->qrisMerchants()->attach(makeMerchant('toko-a'));

    $this->actingAs($seller)->get('/admin/seller-accounts')->assertForbidden();
});
