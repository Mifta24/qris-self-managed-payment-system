<?php

declare(strict_types=1);

use App\Models\User;

it('redirects a guest away from the admin panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('allows any authenticated user to access the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});
