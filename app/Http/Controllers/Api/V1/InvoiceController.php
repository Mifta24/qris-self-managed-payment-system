<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTransactionsRequest;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceCancellationService;
use App\Services\InvoiceExpiryService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceExpiryService $expiryService,
        private readonly InvoiceCancellationService $cancellationService,
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
        return new InvoiceResource($this->expiryService->expireIfDue($this->findInvoice($request, $invoice)));
    }

    public function status(Request $request, string $invoice): JsonResponse
    {
        $model = $this->expiryService->expireIfDue($this->findInvoice($request, $invoice));

        return response()->json([
            'invoice_id' => $model->invoice_number,
            'status' => $model->status->value,
            'expires_at' => $model->expires_at?->toIso8601String(),
            'paid_at' => $model->paid_at?->toIso8601String(),
        ]);
    }

    /**
     * Satu-satunya endpoint v1 yang responsnya dibungkus data/links/meta —
     * pagination Laravel selalu menambahkan wrapper ini terlepas dari
     * JsonResource::withoutWrapping() global. Endpoint resource tunggal
     * lain sengaja tidak dibungkus, lihat docs/api-contract.md.
     */
    public function transactions(ListTransactionsRequest $request): AnonymousResourceCollection
    {
        $invoices = Invoice::where('project_id', $this->project($request)->id)
            ->when($request->validated('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->validated('external_id'), fn ($query, string $externalId) => $query->where('external_id', $externalId))
            ->latest()
            ->paginate((int) ($request->validated('per_page') ?? 20));

        return InvoiceResource::collection($invoices);
    }

    public function cancel(Request $request, string $invoice): InvoiceResource
    {
        // Lazy-expire dulu, sama seperti show()/status() — invoice yang
        // expires_at-nya sudah lewat tapi belum sempat di-flush scheduler
        // harus dianggap expired, bukan dibatalkan.
        $model = $this->expiryService->expireIfDue($this->findInvoice($request, $invoice));

        return new InvoiceResource($this->cancellationService->cancel($model, $this->apiKey($request)));
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

    /**
     * Diisi oleh VerifyApiSignature setelah autentikasi berhasil.
     */
    private function apiKey(Request $request): ApiKey
    {
        return $request->attributes->get('api_key');
    }
}
