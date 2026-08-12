<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autentikasi API key + HMAC sudah ditangani middleware VerifyApiSignature.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'external_id' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
