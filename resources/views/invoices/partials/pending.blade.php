<div class="text-center">
    <p class="text-sm text-gray-500">Total pembayaran</p>
    <p class="mt-1 text-3xl font-semibold tracking-tight">
        Rp{{ number_format($invoice->payment_amount, 0, ',', '.') }}
    </p>
    <p class="mt-1 text-xs text-gray-400">
        Rp{{ number_format($invoice->base_amount, 0, ',', '.') }}
        + kode unik {{ $invoice->unique_code }}
    </p>
    <button
        type="button"
        @click="copyAmount({{ $invoice->payment_amount }})"
        class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-amber-600 hover:text-amber-700"
    >
        <span x-show="!amountCopied">Salin nominal</span>
        <span x-show="amountCopied" x-cloak>Tersalin ✓</span>
    </button>
</div>

<div class="mx-auto mt-6 aspect-square w-full max-w-[260px]">
    <img
        src="{{ route('invoices.qr', $invoice) }}"
        alt="QR pembayaran QRIS"
        class="h-full w-full"
    >
</div>

<p class="mt-4 text-center text-sm text-gray-600">
    Scan QR di atas memakai aplikasi pembayaran QRIS apa pun. Pastikan nominal
    yang tertera sama persis dengan total di atas, termasuk kode uniknya.
</p>

<p class="mt-4 text-center text-sm font-medium text-gray-700">
    Kedaluwarsa dalam <span x-text="remaining" class="tabular-nums"></span>
</p>

@if ($invoice->description)
    <p class="mt-4 border-t border-gray-100 pt-4 text-center text-xs text-gray-400">
        {{ $invoice->description }}
    </p>
@endif
