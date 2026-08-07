<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\ExternalIdConflictException;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Support\Qris\DynamicQrisGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orkestrasi pembuatan invoice: idempotency, alokasi kode unik, generate
 * payload QRIS dinamis, simpan, dan audit log — semuanya dalam satu
 * transaksi. Lihat alur di docs/architecture.md.
 */
final class InvoiceService
{
    public function __construct(
        private readonly UniqueCodeAllocator $allocator,
        private readonly DynamicQrisGenerator $generator,
    ) {}

    /**
     * @param  array{
     *     external_id: string,
     *     amount: int,
     *     description?: ?string,
     *     customer_name?: ?string,
     *     customer_email?: ?string,
     *     customer_phone?: ?string,
     *     callback_url?: ?string,
     *     metadata?: ?array<string, mixed>,
     * }  $data
     *
     * @throws ExternalIdConflictException external_id sudah dipakai dengan nominal berbeda.
     */
    public function create(Project $project, array $data): Invoice
    {
        $existing = Invoice::where('project_id', $project->id)
            ->where('external_id', $data['external_id'])
            ->first();

        if ($existing !== null) {
            if ($existing->base_amount !== $data['amount']) {
                throw ExternalIdConflictException::forExternalId($data['external_id']);
            }

            return $existing;
        }

        return DB::transaction(function () use ($project, $data): Invoice {
            $invoice = $this->allocator->allocate(
                $project,
                $data['amount'],
                fn (int $code, int $paymentAmount): Invoice => $this->persist($project, $data, $code, $paymentAmount),
            );

            AuditLog::create([
                'auditable_type' => Invoice::class,
                'auditable_id' => $invoice->id,
                'event' => 'invoice.created',
                'old_values' => null,
                'new_values' => ['status' => $invoice->status->value],
            ]);

            return $invoice;
        });
    }

    /**
     * @param  array{
     *     external_id: string,
     *     amount: int,
     *     description?: ?string,
     *     customer_name?: ?string,
     *     customer_email?: ?string,
     *     customer_phone?: ?string,
     *     callback_url?: ?string,
     *     metadata?: ?array<string, mixed>,
     * }  $data
     */
    private function persist(Project $project, array $data, int $uniqueCode, int $paymentAmount): Invoice
    {
        $qrisPayload = $this->generator->generate($project->qrisMerchant->static_payload, $paymentAmount);

        return Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'project_id' => $project->id,
            'qris_merchant_id' => $project->qris_merchant_id,
            'external_id' => $data['external_id'],
            'description' => $data['description'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'base_amount' => $data['amount'],
            'unique_code' => $uniqueCode,
            'payment_amount' => $paymentAmount,
            'qris_payload' => $qrisPayload,
            'status' => InvoiceStatus::WaitingPayment,
            'expires_at' => now()->addMinutes($project->invoice_ttl_minutes),
            'callback_url' => $data['callback_url'] ?? $project->callback_url,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Format: INV-{tanggal}-{suffix acak}. Suffix acak dipilih supaya unik
     * secara konstruksi tanpa perlu counter harian yang rawan race condition
     * (lihat catatan di UniqueCodeAllocator soal kenapa retry-after-collision
     * lebih aman daripada SELECT dulu).
     */
    private function generateInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
