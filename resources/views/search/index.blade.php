<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Ambry') }}</title>
    @vite([
        'resources/css/app.css',
        'resources/css/search/index.css',
        'resources/css/search/nav.css',
        'resources/css/shared/circles/search.css',
        'resources/css/search/type-selector.css',
        'resources/js/search/index.js',
    ])
</head>
<body class="search-body">
    @php
        $selectedTypeLabel = $searchTypes[$selectedType] ?? 'Saint';
        $selectedTypePlural = match ($selectedType) {
            'church_father' => 'Church Fathers',
            'blessed' => 'Blesseds',
            'pope' => 'Popes',
            'venerable' => 'Venerables',
            default => 'Saints',
        };
    @endphp

    <main class="search-page">
        @include('search.nav')

        <div class="search-content">
            <div class="search-ornament" aria-hidden="true">
                <span class="search-star search-star-a">✦</span>
                <span class="search-star search-star-b">✦</span>
                <span class="search-star search-star-c">✦</span>
                <span class="search-star search-star-d">✦</span>
                <div class="search-crest">✣</div>
            </div>

            <header class="search-header">
                <h1 class="search-title">
                    <span>Find a</span>
                    @include('search.type-selector', ['types' => $searchTypes, 'selected' => $selectedType, 'form' => 'saint-search-form'])
                </h1>
                <div class="search-rule" aria-hidden="true">
                    <span></span>
                    <span>✧</span>
                    <span></span>
                </div>
                <p class="search-pretitle">Search by name, virtue, or keyword</p>
            </header>

            <section class="search-shell" aria-label="Saint search">
                <form id="saint-search-form" action="{{ route('search.results') }}" method="GET" class="search-panel">
                    <label for="q">Search {{ strtolower($selectedTypePlural) }}</label>
                    <div class="search-input-wrap">
                        <span class="search-icon" aria-hidden="true"></span>
                        <input
                            id="q"
                            name="q"
                            type="search"
                            value="{{ $query }}"
                            placeholder="Search {{ strtolower($selectedTypePlural) }}..."
                            autofocus
                        >
                    </div>
                    <button type="submit" aria-label="Search">
                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                @if ($error)
                    <p class="search-message">{{ $error }}</p>
                @endif

                @if ($searched && ! $error)
                    <div class="search-summary">
                        <span>{{ $results->count() }} {{ \Illuminate\Support\Str::plural('result', $results->count()) }}</span>
                        <span>for “{{ $query }}”</span>
                    </div>

                    <section class="search-results" aria-label="Saint search results">
                        @forelse ($results as $saint)
                            @php
                                $relativePath = "saints/{$saint->slug}.png";
                                $hasImage = file_exists(public_path($relativePath));
                                $fallbackPath = $saint->gender === 'female' ? 'saints/default_female.png' : 'saints/default.png';
                                $imagePath = $hasImage ? $relativePath : $fallbackPath;
                                $displayName = preg_replace('/^(?:Pope\s+)?(?:St\.|Saint)\s+/i', '', $saint->primary_name);
                                $lifeDates = $saint->life_dates;
                            @endphp

                            <article class="search-result">
                                <a class="search-result-link" href="{{ route('saints.profile', $saint) }}">
                                    <span class="search-result-image">
                                        <img src="{{ asset($imagePath) }}" alt="">
                                    </span>
                                    <span class="search-result-content">
                                        <span class="search-result-meta">
                                            <span>{{ $searchTypes[$saint->canonical_status] ?? ucfirst(str_replace('_', ' ', $saint->canonical_status)) }}</span>
                                            @if ($lifeDates)
                                                <span>{{ $lifeDates }}</span>
                                            @endif
                                        </span>
                                        <span class="search-result-title">{{ $displayName }}</span>
                                        @if ($saint->biography)
                                            <span class="search-result-excerpt">
                                                {{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::of($saint->biography)->stripTags()->squish(), 180) }}
                                            </span>
                                        @endif
                                    </span>
                                    <span class="search-result-arrow" aria-hidden="true">→</span>
                                </a>
                            </article>
                        @empty
                            <p class="search-empty">No {{ strtolower($selectedTypePlural) }} matched that search.</p>
                        @endforelse
                    </section>
                @endif
            </section>
        </div>

        <footer class="search-footer search-chrome">
            <span>&copy; {{ now()->year }}</span>
            <span>Maintained by <a href="https://jeremiah.dev">jeremiah.dev</a></span>
        </footer>

        @include('shared.circles.search', ['variant' => 'coral'])
        @include('shared.circles.search', ['variant' => 'ivory'])
        @include('shared.circles.search', ['variant' => 'sage'])
        @include('shared.circles.search', ['variant' => 'gold'])
        @include('shared.circles.search', ['variant' => 'blue'])
    </main>
</body>
</html>
