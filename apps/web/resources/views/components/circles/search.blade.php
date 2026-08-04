@php
    $variant ??= 'coral';
    $variantClass = match ($variant) {
        'ivory' => 'search-circle-ivory',
        'sage' => 'search-circle-sage',
        'gold' => 'search-circle-gold',
        'blue' => 'search-circle-blue',
        default => 'search-circle-coral',
    };
@endphp

<div class="search-circle {{ $variantClass }}" aria-hidden="true"></div>
