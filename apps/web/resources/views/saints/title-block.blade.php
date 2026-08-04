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
        $titleLength >= 14 => ' saint-title--long',
        $titleLength >= 10 => ' saint-title--wide',
        default => '',
    };
@endphp

<header class="saint-title-block">
    <p class="saint-kicker">
        <svg class="saint-cross" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M5 12h14" />
            <path d="M12 5v14" />
        </svg>
        <span class="saint-kicker-label">{{ $kicker }}</span>
    </p>
    <h1 class="saint-title{{ $titleClass }}" style="--saint-title-length: {{ max(1, $titleLength) }};">{{ $name }}</h1>

    @if ($displaySubtitle)
        <p class="saint-subtitle">{{ $displaySubtitle }}</p>
    @endif

    @include('saints.life-dates', [
        'lifeDates' => $lifeDates,
    ])
</header>
