@php
    $subtitle ??= null;
    $lifeDates ??= null;
    $kicker ??= 'Saint';
    $displaySubtitle = $subtitle && ! in_array(strtolower(trim($subtitle)), ['saint', 'st.', 'st', strtolower(trim($kicker))], true)
        ? $subtitle
        : null;
    $titleLength = mb_strlen($name);
    $titleClass = match (true) {
        $titleLength >= 24 => ' saint-title--compact',
        $titleLength >= 18 => ' saint-title--long',
        default => '',
    };
@endphp

<header class="saint-title-block">
    <p class="saint-kicker">
        <span class="saint-cross">+</span>
        <span>{{ $kicker }}</span>
    </p>
    <h1 class="saint-title{{ $titleClass }}">{{ $name }}</h1>

    @if ($displaySubtitle)
        <p class="saint-subtitle">{{ $displaySubtitle }}</p>
    @endif

    @include('saints.life-dates', [
        'lifeDates' => $lifeDates,
    ])
</header>
