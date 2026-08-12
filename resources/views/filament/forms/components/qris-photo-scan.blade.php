@php
    $targetStatePath = \Illuminate\Support\Str::beforeLast($field->getStatePath(), '.') . '.static_payload';
@endphp

{!! $field->wrapEmbeddedHtml(
    view('filament.forms.components.qris-photo-scan-content', ['targetStatePath' => $targetStatePath])->render()
) !!}
