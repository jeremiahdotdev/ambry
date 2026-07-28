@php
    $subtitle ??= null;
    $lifeDates ??= null;
    $kicker ??= 'Saint';
    $displaySubtitle = $subtitle && ! in_array(strtolower(trim($subtitle)), ['saint', 'st.', 'st', strtolower(trim($kicker))], true)
        ? $subtitle
        : null;
@endphp

<header class="saint-title-block">
    <p class="saint-kicker">
        <span class="saint-cross">+</span>
        <span>{{ $kicker }}</span>
    </p>
    <h1>{{ $name }}</h1>

    @if ($displaySubtitle)
        <p class="saint-subtitle">{{ $displaySubtitle }}</p>
    @endif

    @include('saints.life-dates', [
        'lifeDates' => $lifeDates,
    ])
</header>
