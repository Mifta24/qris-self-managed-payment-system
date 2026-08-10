<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceExpiryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Halaman pembayaran publik. Tidak lewat VerifyApiSignature — invoice_number
 * berperan sebagai identifier publik (tanggal + suffix acak, tidak tertebak),
 * dan yang diekspos hanya field turunan, tidak pernah qris_payload.
 */
final class InvoicePageController extends Controller
{
    public function __construct(
        private readonly InvoiceExpiryService $expiryService,
    ) {}

    public function show(Invoice $invoice): View
    {
        $invoice = $this->expiryService->expireIfDue($invoice);

        return view('invoices.show', ['invoice' => $invoice]);
    }

    public function status(Invoice $invoice): JsonResponse
    {
        $invoice = $this->expiryService->expireIfDue($invoice);

        return response()->json([
            'status' => $invoice->status->value,
            'expires_at' => $invoice->expires_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ]);
    }
}
