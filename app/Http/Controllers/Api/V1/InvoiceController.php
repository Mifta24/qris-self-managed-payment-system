<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($this->project($request), $request->validated());

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode($invoice->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, string $invoice): InvoiceResource
    {
        return new InvoiceResource($this->findInvoice($request, $invoice));
    }

    public function status(Request $request, string $invoice): JsonResponse
    {
        $model = $this->findInvoice($request, $invoice);

        return response()->json([
            'invoice_id' => $model->invoice_number,
            'status' => $model->status->value,
            'expires_at' => $model->expires_at?->toIso8601String(),
            'paid_at' => $model->paid_at?->toIso8601String(),
        ]);
    }

    private function findInvoice(Request $request, string $invoiceNumber): Invoice
    {
        return Invoice::where('project_id', $this->project($request)->id)
            ->where('invoice_number', $invoiceNumber)
            ->firstOrFail();
    }

    /**
     * Diisi oleh VerifyApiSignature setelah autentikasi berhasil.
     */
    private function project(Request $request): Project
    {
        return $request->attributes->get('project');
    }
}
