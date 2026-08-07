<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;

it('allows the documented transitions', function (InvoiceStatus $from, InvoiceStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    'created -> waiting_payment' => [InvoiceStatus::Created, InvoiceStatus::WaitingPayment],
    'created -> failed' => [InvoiceStatus::Created, InvoiceStatus::Failed],
    'waiting_payment -> manual_verification' => [InvoiceStatus::WaitingPayment, InvoiceStatus::ManualVerification],
    'waiting_payment -> paid' => [InvoiceStatus::WaitingPayment, InvoiceStatus::Paid],
    'waiting_payment -> expired' => [InvoiceStatus::WaitingPayment, InvoiceStatus::Expired],
    'waiting_payment -> cancelled' => [InvoiceStatus::WaitingPayment, InvoiceStatus::Cancelled],
    'manual_verification -> paid' => [InvoiceStatus::ManualVerification, InvoiceStatus::Paid],
    'manual_verification -> waiting_payment' => [InvoiceStatus::ManualVerification, InvoiceStatus::WaitingPayment],
    'expired -> paid (pembayaran terlambat)' => [InvoiceStatus::Expired, InvoiceStatus::Paid],
]);

it('rejects transitions outside the state machine', function (InvoiceStatus $from, InvoiceStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'created -> paid (lompat verifikasi)' => [InvoiceStatus::Created, InvoiceStatus::Paid],
    'created -> expired' => [InvoiceStatus::Created, InvoiceStatus::Expired],
    'waiting_payment -> failed' => [InvoiceStatus::WaitingPayment, InvoiceStatus::Failed],
    'paid -> waiting_payment' => [InvoiceStatus::Paid, InvoiceStatus::WaitingPayment],
    'paid -> cancelled' => [InvoiceStatus::Paid, InvoiceStatus::Cancelled],
    'cancelled -> paid' => [InvoiceStatus::Cancelled, InvoiceStatus::Paid],
    'failed -> waiting_payment' => [InvoiceStatus::Failed, InvoiceStatus::WaitingPayment],
    'expired -> waiting_payment' => [InvoiceStatus::Expired, InvoiceStatus::WaitingPayment],
]);

it('never allows a status to transition to itself', function (InvoiceStatus $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with('all statuses');

it('marks paid, cancelled, and failed as terminal', function () {
    expect(InvoiceStatus::Paid->isTerminal())->toBeTrue()
        ->and(InvoiceStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(InvoiceStatus::Failed->isTerminal())->toBeTrue();
});

it('marks created, waiting_payment, manual_verification, and expired as non-terminal', function () {
    expect(InvoiceStatus::Created->isTerminal())->toBeFalse()
        ->and(InvoiceStatus::WaitingPayment->isTerminal())->toBeFalse()
        ->and(InvoiceStatus::ManualVerification->isTerminal())->toBeFalse()
        ->and(InvoiceStatus::Expired->isTerminal())->toBeFalse();
});

it('locks the nominal only for statuses covered by the active payment_amount index', function () {
    expect(InvoiceStatus::Created->locksAmount())->toBeTrue()
        ->and(InvoiceStatus::WaitingPayment->locksAmount())->toBeTrue()
        ->and(InvoiceStatus::ManualVerification->locksAmount())->toBeTrue()
        ->and(InvoiceStatus::Paid->locksAmount())->toBeFalse()
        ->and(InvoiceStatus::Expired->locksAmount())->toBeFalse()
        ->and(InvoiceStatus::Cancelled->locksAmount())->toBeFalse()
        ->and(InvoiceStatus::Failed->locksAmount())->toBeFalse();
});

dataset('all statuses', fn () => array_map(
    static fn (InvoiceStatus $status): array => [$status],
    InvoiceStatus::cases(),
));
