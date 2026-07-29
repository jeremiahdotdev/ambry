<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Ambry') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite([
        'resources/css/app.css',
        'resources/css/search/index.css',
        'resources/css/search/nav.css',
        'resources/css/shared/circles/search.css',
        'resources/css/search/type-selector.css',
        'resources/css/search/results.css',
        'resources/js/search/index.js',
    ])
</head>
<body class="search-body">
    @php
        $selectedTypeLabel = $searchTypes[$selectedType] ?? 'Saint';
        $selectedTypePlural = match ($selectedType) {
            'blessed' => 'Blesseds',
            'pope' => 'Popes',
            'venerable' => 'Venerables',
            default => 'Saints',
        };
        $popularSearches ??= [];
        $selectedPopularSearch ??= null;
        $selectedPopularLabel = $selectedPopularSearch ? ($popularSearches[$selectedPopularSearch]['label'] ?? null) : null;
    @endphp

    <main class="search-page">
        @include('search.nav')

        <div class="search-content">
            <div class="search-ornament" aria-hidden="true">
                <span class="search-star search-star-a">✦</span>
                <span class="search-star search-star-b">✦</span>
                <span class="search-star search-star-c">✦</span>
                <span class="search-star search-star-d">✦</span>
                <div class="search-crest">
                    <span>✣</span>
                </div>
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
                <p class="search-pretitle">Search by name, virtue, or patronage.</p>
            </header>

            <section class="search-shell" aria-label="Saint search">
                <form id="saint-search-form" action="{{ route('search.results') }}" method="GET" class="search-panel">
                    <label for="q">Search...</label>
                    <div class="search-input-wrap">
                        <span class="search-icon" aria-hidden="true"></span>
                        <input
                            id="q"
                            name="q"
                            type="search"
                            value="{{ $query }}"
                            placeholder="Search..."
                            autofocus
                        >
                    </div>
                    <button type="submit" aria-label="Search">
                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </button>
                    <input
                        type="hidden"
                        name="popular"
                        value="{{ $selectedPopularSearch ?? '' }}"
                        data-popular-filter-input
                        @if (! $selectedPopularSearch) disabled @endif
                    >
                </form>

                @if ($popularSearches)
                    <div class="popular-searches" aria-label="Popular filters" data-popular-filters>
                        <p>Popular Filters</p>
                        <div class="popular-search-list">
                            @foreach ($popularSearches as $popularKey => $popularSearch)
                                <button
                                    type="button"
                                    class="popular-search-link {{ $selectedPopularSearch === $popularKey ? 'is-active' : '' }}"
                                    aria-pressed="{{ $selectedPopularSearch === $popularKey ? 'true' : 'false' }}"
                                    data-popular-filter
                                    data-value="{{ $popularKey }}"
                                >
                                    <i data-lucide="{{ $popularSearch['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $popularSearch['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($error)
                    <p class="search-message">{{ $error }}</p>
                @endif

                @if ($searched && ! $error)
                    <div class="search-summary">
                        @php($resultCount = method_exists($results, 'total') ? $results->total() : $results->count())
                        <span>{{ $resultCount }} {{ \Illuminate\Support\Str::plural('result', $resultCount) }}</span>
                        @if ($query !== '')
                            <span>for "{{ $query }}"</span>
                        @elseif ($selectedPopularLabel)
                            <span>for {{ $selectedPopularLabel }}</span>
                        @else
                            <span>showing all {{ strtolower($selectedTypePlural) }}</span>
                        @endif
                    </div>

                    @include('search.results', [
                        'results' => $results,
                        'searchTypes' => $searchTypes,
                        'selectedTypePlural' => $selectedTypePlural,
                        'selectedPopularLabel' => $selectedPopularLabel,
                    ])
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
