<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Filament\Seller\Resources\Payments\Pages\ListPayments;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Models\User;
use App\Services\InvoiceService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\QrisTestPayload;

function makeShop(string $code): array
{
    $merchant = QrisMerchant::create([
        'code' => $code,
        'label' => 'Toko '.$code,
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $project = Project::create([
        'slug' => 'proj-'.$code,
        'name' => 'Project '.$code,
        'qris_merchant_id' => $merchant->id,
        'invoice_ttl_minutes' => 30,
        'unique_code_min' => 1,
        'unique_code_max' => 999,
    ]);

    return [$merchant, $project];
}

beforeEach(function () {
    [$this->merchantA, $projectA] = makeShop('toko-a');
    [$this->merchantB, $projectB] = makeShop('toko-b');

    $this->invoiceA = app(InvoiceService::class)->create($projectA, ['external_id' => 'A-1', 'amount' => 100000]);
    $this->invoiceB = app(InvoiceService::class)->create($projectB, ['external_id' => 'B-1', 'amount' => 50000]);

    $this->seller = User::factory()->create();
    $this->seller->qrisMerchants()->attach($this->merchantA);

    $this->actingAs($this->seller);
    Filament::setCurrentPanel(Filament::getPanel('seller'));
    Filament::setTenant($this->merchantA);
    Filament::bootCurrentPanel();
});

function confirmData(Invoice $invoice, array $override = []): array
{
    return array_merge([
        'confirmed_amount' => $invoice->payment_amount,
        'confirmation_reference' => 'RRN123456',
        'paid_at' => now()->toDateTimeString(),
    ], $override);
}

it('lists only the invoices of the seller shop', function () {
    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$this->invoiceA])
        ->assertCanNotSeeTableRecords([$this->invoiceB]);
});

it('lets the seller confirm a payment and records who did it', function () {
    Livewire::test(ListPayments::class)
        ->callTableAction('confirm', $this->invoiceA, data: confirmData($this->invoiceA))
        ->assertHasNoTableActionErrors();

    $invoice = $this->invoiceA->fresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->confirmed_by)->toBe($this->seller->id)
        ->and($invoice->confirmation_reference)->toBe('RRN123456');

    expect(AuditLog::where('auditable_id', $invoice->id)->where('event', 'invoice.paid')->where('user_id', $this->seller->id)->exists())
        ->toBeTrue();
});

it('rejects a confirmation whose amount does not match', function () {
    Livewire::test(ListPayments::class)
        ->callTableAction('confirm', $this->invoiceA, data: confirmData($this->invoiceA, [
            'confirmed_amount' => $this->invoiceA->payment_amount + 1,
        ]));

    expect($this->invoiceA->fresh()->status)->toBe(InvoiceStatus::WaitingPayment);
});

it('requires a note for an expired invoice', function () {
    $this->invoiceA->forceFill(['status' => InvoiceStatus::Expired])->save();

    Livewire::test(ListPayments::class)
        ->set('activeTab', 'expired')
        ->callTableAction('confirm', $this->invoiceA, data: confirmData($this->invoiceA))
        ->assertHasTableActionErrors(['confirmation_note' => 'required']);

    expect($this->invoiceA->fresh()->status)->toBe(InvoiceStatus::Expired);
});

it('does not offer the confirm action for a paid invoice', function () {
    $this->invoiceA->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

    Livewire::test(ListPayments::class)
        ->set('activeTab', 'paid')
        ->assertTableActionHidden('confirm', $this->invoiceA);
});

it('cannot confirm an invoice that belongs to another shop', function () {
    // Panggilan mentah ke Livewire, seolah klien memalsukan id record milik toko lain.
    try {
        Livewire::test(ListPayments::class)
            ->call('mountTableAction', 'confirm', (string) $this->invoiceB->getKey())
            ->call('callMountedTableAction', confirmData($this->invoiceB));
    } catch (Throwable) {
        // Boleh gagal dengan error apa pun; yang dijaga adalah status invoice di bawah.
    }

    expect($this->invoiceB->fresh()->status)->toBe(InvoiceStatus::WaitingPayment)
        ->and($this->invoiceB->fresh()->confirmed_by)->toBeNull();
});

it('renders only the seller shop invoices over HTTP', function () {
    $this->invoiceA->forceFill(['customer_name' => 'Pelanggan Toko A'])->save();
    $this->invoiceB->forceFill(['customer_name' => 'Pelanggan Toko B'])->save();

    $this->get('/toko/toko-a/pembayaran')
        ->assertSuccessful()
        ->assertSee('Pelanggan Toko A')
        ->assertDontSee('Pelanggan Toko B');

    $this->get('/toko/toko-b/pembayaran')->assertNotFound();
});

it('shows waiting invoices on the default tab and paid ones on the paid tab', function () {
    $this->invoiceA->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

    Livewire::test(ListPayments::class)
        ->assertCanNotSeeTableRecords([$this->invoiceA])
        ->set('activeTab', 'paid')
        ->assertCanSeeTableRecords([$this->invoiceA]);
});

it('never renders the QRIS payload', function () {
    $html = Livewire::test(ListPayments::class)->html();

    expect($html)->not->toContain($this->invoiceA->qris_payload)
        ->and($html)->not->toContain(QrisTestPayload::static());
});
