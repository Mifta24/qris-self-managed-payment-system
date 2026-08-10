<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_id' => $this->invoice_number,
            'external_id' => $this->external_id,
            'base_amount' => $this->base_amount,
            'unique_code' => $this->unique_code,
            'payment_amount' => $this->payment_amount,
            'status' => $this->status->value,
            'payment_url' => url("/invoice/{$this->invoice_number}"),
            'qr_image_url' => url("/qris/{$this->invoice_number}.svg"),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
