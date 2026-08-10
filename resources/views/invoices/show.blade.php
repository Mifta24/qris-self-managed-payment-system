<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pembayaran {{ $invoice->invoice_number }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-gray-50 p-4 font-sans text-gray-900">
        @php
            $partial = match ($invoice->status->value) {
                'paid' => 'paid',
                'expired' => 'expired',
                'cancelled' => 'cancelled',
                'failed' => 'failed',
                default => 'pending',
            };
        @endphp

        <div
            x-data="paymentPage({
                status: @js($invoice->status->value),
                expiresAt: @js($invoice->expires_at?->toIso8601String()),
                statusUrl: @js(route('invoices.status', $invoice)),
            })"
            class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-sm"
        >
            @include('invoices.partials.'.$partial, ['invoice' => $invoice])
        </div>
    </body>
</html>
