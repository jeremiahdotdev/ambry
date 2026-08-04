@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'autofocus' => false,
    'required' => true,
    'maxlength' => null,
])

<div class="form-input-field">
    <label class="form-input-label" for="{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value ?? old($name) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($autofocus) autofocus @endif
        @if ($required) required @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        {{ $attributes->class('form-input-control') }}
    >
</div>
