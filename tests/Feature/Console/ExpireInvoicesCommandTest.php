<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\InvoiceService;
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
});

function makeInvoiceForCommand(string $externalId, int $amount): Invoice
{
    return app(InvoiceService::class)->create(test()->project, [
        'external_id' => $externalId,
        'amount' => $amount,
    ]);
}

it('expires only waiting_payment invoices whose expires_at has passed', function () {
    $due = makeInvoiceForCommand('ORDER-DUE', 100000);
    $due->forceFill(['expires_at' => now()->subMinute()])->save();

    $notDueYet = makeInvoiceForCommand('ORDER-NOT-DUE', 200000);

    $alreadyPaid = makeInvoiceForCommand('ORDER-PAID', 300000);
    $alreadyPaid->forceFill([
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
        'expires_at' => now()->subMinute(),
    ])->save();

    $manualVerification = makeInvoiceForCommand('ORDER-MV', 400000);
    $manualVerification->forceFill([
        'status' => InvoiceStatus::ManualVerification,
        'expires_at' => now()->subMinute(),
    ])->save();

    $this->artisan('invoices:expire')->assertSuccessful();

    expect($due->fresh()->status)->toBe(InvoiceStatus::Expired)
        ->and($notDueYet->fresh()->status)->toBe(InvoiceStatus::WaitingPayment)
        ->and($alreadyPaid->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($manualVerification->fresh()->status)->toBe(InvoiceStatus::ManualVerification);

    expect(AuditLog::where('auditable_id', $due->id)->where('event', 'invoice.expired')->exists())->toBeTrue();
    expect(AuditLog::where('event', 'invoice.expired')->count())->toBe(1);
});

it('reports how many invoices were expired', function () {
    $first = makeInvoiceForCommand('ORDER-1', 100000);
    $first->forceFill(['expires_at' => now()->subMinute()])->save();

    $second = makeInvoiceForCommand('ORDER-2', 200000);
    $second->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('invoices:expire')
        ->expectsOutputToContain('2 invoice dipindahkan ke status expired.')
        ->assertSuccessful();
});
