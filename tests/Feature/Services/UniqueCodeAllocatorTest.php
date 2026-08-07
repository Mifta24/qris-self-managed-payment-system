<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Exceptions\NoAvailableAmountException;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\QrisMerchant;
use App\Services\UniqueCodeAllocator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\Support\QrisTestPayload;

beforeEach(function () {
    $this->merchant = QrisMerchant::create([
        'code' => 'gopay-utama',
        'label' => 'GoPay Merchant',
        'static_payload' => QrisTestPayload::static(),
        'currency' => '360',
    ]);

    $this->project = Project::create([
        'slug' => 'restoran-a',
        'name' => 'Restoran A',
        'qris_merchant_id' => $this->merchant->id,
        'unique_code_min' => 1,
        'unique_code_max' => 5,
    ]);

    $this->allocator = new UniqueCodeAllocator;
});

function persistStubInvoice(Project $project, QrisMerchant $merchant, int $uniqueCode, int $paymentAmount): Invoice
{
    return Invoice::create([
        'invoice_number' => 'INV-'.Str::random(10),
        'project_id' => $project->id,
        'qris_merchant_id' => $merchant->id,
        'external_id' => 'ORDER-'.Str::random(10),
        'base_amount' => $paymentAmount - $uniqueCode,
        'unique_code' => $uniqueCode,
        'payment_amount' => $paymentAmount,
        'qris_payload' => 'stub-payload-'.$uniqueCode,
        'status' => InvoiceStatus::WaitingPayment,
    ]);
}

it('allocates a free code on the first attempt when the range is empty', function () {
    $invoice = $this->allocator->allocate(
        $this->project,
        100000,
        fn (int $code, int $amount) => persistStubInvoice($this->project, $this->merchant, $code, $amount),
    );

    expect($invoice->unique_code)->toBeGreaterThanOrEqual(1)
        ->and($invoice->unique_code)->toBeLessThanOrEqual(5)
        ->and($invoice->payment_amount)->toBe(100000 + $invoice->unique_code);
});

it('retries past collisions until it lands on the only free code left', function () {
    // Kunci semua kode 1-4 di rentang 1-5, sisakan hanya kode 5.
    foreach (range(1, 4) as $code) {
        persistStubInvoice($this->project, $this->merchant, $code, 100000 + $code);
    }

    $invoice = $this->allocator->allocate(
        $this->project,
        100000,
        fn (int $code, int $amount) => persistStubInvoice($this->project, $this->merchant, $code, $amount),
    );

    expect($invoice->unique_code)->toBe(5)
        ->and($invoice->payment_amount)->toBe(100005);
});

it('throws NoAvailableAmountException once the entire range is exhausted', function () {
    foreach (range(1, 5) as $code) {
        persistStubInvoice($this->project, $this->merchant, $code, 100000 + $code);
    }

    $this->allocator->allocate(
        $this->project,
        100000,
        fn (int $code, int $amount) => persistStubInvoice($this->project, $this->merchant, $code, $amount),
    );
})->throws(NoAvailableAmountException::class);

it('does not lock a different nominal that happens to be occupied', function () {
    // payment_amount 200001 dipakai nominal lain; tidak boleh menghalangi
    // alokasi untuk base_amount 100000 di kode 1.
    persistStubInvoice($this->project, $this->merchant, 1, 200001);

    $invoice = $this->allocator->allocate(
        $this->project,
        100000,
        fn (int $code, int $amount) => persistStubInvoice($this->project, $this->merchant, $code, $amount),
    );

    expect($invoice->payment_amount)->toBe(100000 + $invoice->unique_code);
});

it('does not swallow database errors unrelated to the payment_amount collision', function () {
    persistStubInvoice($this->project, $this->merchant, 1, 100001);

    $this->allocator->allocate(
        $this->project,
        100000,
        function (int $code, int $amount) {
            // Sengaja memicu unique violation yang tidak berkaitan dengan
            // payment_amount (invoice_number sama persis), untuk memastikan
            // allocator tidak salah menganggapnya sebagai bentrok kode unik.
            Invoice::create([
                'invoice_number' => 'INV-DUPLICATE',
                'project_id' => $this->project->id,
                'qris_merchant_id' => $this->merchant->id,
                'external_id' => 'ORDER-A',
                'base_amount' => 100000,
                'unique_code' => $code,
                'payment_amount' => $amount,
                'qris_payload' => 'stub',
                'status' => InvoiceStatus::WaitingPayment,
            ]);

            return Invoice::create([
                'invoice_number' => 'INV-DUPLICATE',
                'project_id' => $this->project->id,
                'qris_merchant_id' => $this->merchant->id,
                'external_id' => 'ORDER-B',
                'base_amount' => 100000,
                'unique_code' => $code,
                'payment_amount' => $amount + 1,
                'qris_payload' => 'stub',
                'status' => InvoiceStatus::WaitingPayment,
            ]);
        },
    );
})->throws(QueryException::class);
