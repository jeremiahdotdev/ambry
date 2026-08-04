@props([
    'type' => 'submit',
])

<button type="{{ $type }}" {{ $attributes->class('form-button') }}>
    {{ $slot }}
</button>
