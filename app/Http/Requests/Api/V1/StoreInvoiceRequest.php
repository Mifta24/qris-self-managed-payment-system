<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInvoiceRequest extends FormRequest
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
            'external_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'callback_url' => ['nullable', 'url', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
