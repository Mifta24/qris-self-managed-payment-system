<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\CallbackLog;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Str;
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
        'invoice_ttl_minutes' => 30,
        'unique_code_min' => 1,
        'unique_code_max' => 999,
    ]);

    $this->admin = User::factory()->create();
    $this->invoice = app(InvoiceService::class)->create($this->project, [
        'external_id' => 'ORDER-1',
        'amount' => 100000,
    ]);

    $this->actingAs($this->admin);
});

it('lists invoices in the verification queue', function () {
    Livewire::test(ListInvoices::class)
        ->assertCanSeeTableRecords([$this->invoice]);
});

it('confirms a payment through the table action with valid data', function () {
    Livewire::test(ListInvoices::class)
        ->callTableAction('confirm', $this->invoice, data: [
            'confirmed_amount' => $this->invoice->payment_amount,
            'confirmation_reference' => 'RRN123456',
            'paid_at' => now()->toDateTimeString(),
        ])
        ->assertHasNoTableActionErrors();

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('rejects a mismatched confirmed_amount through the table action', function () {
    Livewire::test(ListInvoices::class)
        ->callTableAction('confirm', $this->invoice, data: [
            'confirmed_amount' => $this->invoice->payment_amount + 1,
            'confirmation_reference' => 'RRN123456',
            'paid_at' => now()->toDateTimeString(),
        ]);

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::WaitingPayment);
});

it('does not offer the confirm action for a paid invoice', function () {
    $this->invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

    Livewire::test(ListInvoices::class)
        ->removeTableFilters()
        ->assertTableActionHidden('confirm', $this->invoice);
});

it('filters the table to invoices whose callback failed for good', function () {
    $failed = $this->invoice;
    $failed->forceFill(['callback_failed_at' => now()])->save();

    $stillOk = app(InvoiceService::class)->create($this->project, [
        'external_id' => 'ORDER-2',
        'amount' => 200000,
    ]);

    Livewire::test(ListInvoices::class)
        ->removeTableFilters()
        ->filterTable('callback_gagal')
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$stillOk]);
});

it('shows a navigation badge counting invoices with a failed callback', function () {
    expect(InvoiceResource::getNavigationBadge())->toBeNull();

    $this->invoice->forceFill(['callback_failed_at' => now()])->save();

    expect(InvoiceResource::getNavigationBadge())->toBe('1');
});

it('lists callback attempts in the invoice infolist', function () {
    CallbackLog::create([
        'invoice_id' => $this->invoice->id,
        'event_id' => (string) Str::uuid(),
        'event_type' => 'invoice.paid',
        'url' => 'https://project.example.com/callback',
        'attempt' => 1,
        'request_payload' => ['invoice_id' => $this->invoice->invoice_number],
        'request_signature' => 'signature',
        'response_status' => 500,
        'error_message' => null,
        'succeeded_at' => null,
    ]);

    Livewire::test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSeeText('Riwayat callback')
        ->assertSeeText('invoice.paid');
});
