<div class="text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-600">
        ✓
    </div>
    <p class="mt-4 text-lg font-semibold">Pembayaran diterima</p>
    <p class="mt-1 text-sm text-gray-500">
        Rp{{ number_format($invoice->payment_amount, 0, ',', '.') }} untuk invoice
        {{ $invoice->invoice_number }}
    </p>
    @if ($invoice->paid_at)
        <p class="mt-1 text-xs text-gray-400">
            Dibayar pada {{ $invoice->paid_at->translatedFormat('d M Y, H:i') }}
        </p>
    @endif
</div>
