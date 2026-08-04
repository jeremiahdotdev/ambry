<x-blank-page
    :title="'Search Results | '.config('app.name', 'Ambry')"
    page-class="search-results-page"
    :assets="[
        'resources/css/search/results.css',
        'resources/js/search/index.js',
    ]"
>
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

    <div class="search-results-shell">
        @include('components.back-to-search', [
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

    <x-slot:circles>
        <div class="search-circle search-results-circle-marigold" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-plum" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-moss" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-rose" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-cream" aria-hidden="true"></div>
    </x-slot:circles>
</x-blank-page>
