<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Halaman pembayaran publik. Tidak lewat VerifyApiSignature — invoice_number
 * berperan sebagai identifier publik (tanggal + suffix acak, tidak tertebak),
 * dan yang diekspos hanya field turunan, tidak pernah qris_payload.
 */
final class InvoicePageController extends Controller
{
    public function show(Invoice $invoice): View
    {
        return view('invoices.show', ['invoice' => $invoice]);
    }

    public function status(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'status' => $invoice->status->value,
            'expires_at' => $invoice->expires_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ]);
    }
}
