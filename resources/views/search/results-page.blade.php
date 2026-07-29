<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search Results | {{ config('app.name', 'Ambry') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite([
        'resources/css/app.css',
        'resources/css/search/index.css',
        'resources/css/search/nav.css',
        'resources/css/shared/circles/search.css',
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
        $selectedTypeHeading = match ($selectedType) {
            'pope' => 'Popes',
            'blessed' => 'Blessed',
            'venerable' => 'Venerable',
            default => 'Saints',
        };
        $popularSearches ??= [];
        $selectedPopularSearch ??= null;
        $selectedPopularLabel = $selectedPopularSearch ? ($popularSearches[$selectedPopularSearch]['label'] ?? null) : null;
        $backQuery = array_filter([
            'type' => $selectedType !== 'saint' ? $selectedType : null,
            'popular' => $selectedPopularSearch,
        ]);
    @endphp

    <main class="search-page search-results-page">
        <div class="search-results-shell">
            @include('shared.back-to-search', [
                'class' => 'search-back-link',
                'href' => route('search.index', $backQuery),
            ])

            <header class="search-results-header">
                <p>{{ $selectedPopularLabel ? "{$selectedTypeHeading} who were" : 'Searching' }}</p>
                <h1>{{ $selectedPopularLabel ?? $selectedTypeHeading }}</h1>
                @if (! $error)
                    <div class="search-summary">
                        @php($resultCount = method_exists($results, 'total') ? $results->total() : $results->count())
                        <span>{{ $resultCount }}{{ $resultCount >= 10 ? '+' : '' }} {{ \Illuminate\Support\Str::plural('result', $resultCount) }}</span>
                    </div>
                @endif
            </header>

            @if ($error)
                <p class="search-message">{{ $error }}</p>
            @else
                @include('search.results', [
                    'results' => $results,
                    'searchTypes' => $searchTypes,
                    'selectedTypePlural' => $selectedTypePlural,
                    'selectedPopularLabel' => $selectedPopularLabel,
                ])
            @endif
        </div>

        @include('shared.circles.search', ['variant' => 'coral'])
        @include('shared.circles.search', ['variant' => 'ivory'])
        @include('shared.circles.search', ['variant' => 'sage'])
        @include('shared.circles.search', ['variant' => 'gold'])
        @include('shared.circles.search', ['variant' => 'blue'])
        <div class="search-circle search-results-circle-marigold" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-plum" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-moss" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-rose" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-cream" aria-hidden="true"></div>
    </main>
</body>
</html>
