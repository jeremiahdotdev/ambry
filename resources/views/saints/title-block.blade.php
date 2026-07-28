@php
    $subtitle ??= null;
    $lifeDates ??= null;
    $displayName = preg_replace('/^(?:Pope\s+)?(?:St\.|Saint)\s+/i', '', $name);
    $displaySubtitle = $subtitle && ! in_array(strtolower(trim($subtitle)), ['saint', 'st.', 'st'], true)
        ? $subtitle
        : null;
@endphp

<header class="saint-title-block">
    <p class="saint-kicker">
        <span class="saint-cross">+</span>
        <span>Saint</span>
    </p>
    <h1>{{ $displayName }}</h1>

    @if ($displaySubtitle)
        <p class="saint-subtitle">{{ $displaySubtitle }}</p>
    @endif

    @include('saints.life-dates', [
        'lifeDates' => $lifeDates,
    ])
    <span class="saint-divider"></span>
</header>
