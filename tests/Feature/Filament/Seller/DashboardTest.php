<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Filament\Seller\Widgets\ShopOverviewWidget;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Models\User;
use App\Services\InvoiceService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\QrisTestPayload;

it('sums only the paid money of the seller shop for today', function () {
    $make = function (string $code): array {
        $merchant = QrisMerchant::create([
            'code' => $code, 'label' => $code, 'static_payload' => QrisTestPayload::static(), 'currency' => '360',
        ]);
        $project = Project::create([
            'slug' => 'p-'.$code, 'name' => $code, 'qris_merchant_id' => $merchant->id,
            'invoice_ttl_minutes' => 30, 'unique_code_min' => 1, 'unique_code_max' => 999,
        ]);

        return [$merchant, $project];
    };

    [$mine, $myProject] = $make('toko-a');
    [, $otherProject] = $make('toko-b');

    $paid = app(InvoiceService::class)->create($myProject, ['external_id' => 'A-1', 'amount' => 100000]);
    $paid->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

    $waiting = app(InvoiceService::class)->create($myProject, ['external_id' => 'A-2', 'amount' => 20000]);

    $other = app(InvoiceService::class)->create($otherProject, ['external_id' => 'B-1', 'amount' => 777000]);
    $other->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

    $seller = User::factory()->create();
    $seller->qrisMerchants()->attach($mine);
    $this->actingAs($seller);
    Filament::setCurrentPanel(Filament::getPanel('seller'));
    Filament::setTenant($mine);
    Filament::bootCurrentPanel();

    $html = Livewire::test(ShopOverviewWidget::class)->html();

    $expected = number_format($paid->payment_amount, 0, ',', '.');

    expect($html)->toContain('Rp '.$expected)
        ->and($html)->not->toContain(number_format($other->payment_amount, 0, ',', '.'));
});
