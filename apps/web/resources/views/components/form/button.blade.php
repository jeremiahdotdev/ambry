@props([
    'type' => 'submit',
    'variant' => 'primary',
])

@php
    $variantClass = match ($variant) {
        'danger' => 'form-button-danger',
        default => 'form-button-primary',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->class(['form-button', $variantClass]) }}>
    {{ $slot }}
</button>
