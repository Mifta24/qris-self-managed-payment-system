<?php

declare(strict_types=1);

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $this->merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $this->actingAs(User::factory()->create());
});

it('lists projects', function () {
    $project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $this->merchant->id,
    ]);

    Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$project]);
});

it('creates a project through the form', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'slug' => 'restoran-b',
            'name' => 'Restoran B',
            'qris_merchant_id' => $this->merchant->id,
            'invoice_ttl_minutes' => 30,
            'unique_code_min' => 1,
            'unique_code_max' => 999,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::where('slug', 'restoran-b')->exists())->toBeTrue();
});

it('rejects unique_code_max below unique_code_min', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'slug' => 'restoran-c',
            'name' => 'Restoran C',
            'qris_merchant_id' => $this->merchant->id,
            'invoice_ttl_minutes' => 30,
            'unique_code_min' => 500,
            'unique_code_max' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['unique_code_max']);
});

it('auto-generates a non-blank callback_signing_secret when creating a project', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'slug' => 'restoran-d',
            'name' => 'Restoran D',
            'qris_merchant_id' => $this->merchant->id,
            'invoice_ttl_minutes' => 30,
            'unique_code_min' => 1,
            'unique_code_max' => 999,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = Project::where('slug', 'restoran-d')->firstOrFail();

    expect($project->callback_signing_secret)->not->toBeNull()
        ->and($project->callback_signing_secret)->not->toBe('');
});

it('locks the callback_signing_secret field on edit', function () {
    $project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $this->merchant->id,
        'callback_signing_secret' => 'existing-secret',
    ]);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertFormFieldIsDisabled('callback_signing_secret');
});

it('locks the slug field on edit', function () {
    $project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $this->merchant->id,
    ]);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->assertFormFieldIsDisabled('slug');
});
