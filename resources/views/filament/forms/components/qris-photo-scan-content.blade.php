@vite('resources/js/qris-scanner.js')

<div
    x-data="qrisPhotoScan()"
    x-init="targetStatePath = @js($targetStatePath)"
>
    <x-filament::input.wrapper>
        <x-filament::input
            type="file"
            accept="image/*"
            capture="environment"
            x-on:change="handleFile($event)"
            x-bind:disabled="isProcessing"
        />
    </x-filament::input.wrapper>

    <p x-show="isProcessing" x-cloak class="fi-fo-field-hint-text">
        Membaca foto&hellip;
    </p>

    <p
        x-show="error"
        x-cloak
        x-text="error"
        style="color: rgb(var(--danger-600)); margin-top: 0.375rem; font-size: 0.875rem;"
    ></p>
</div>
