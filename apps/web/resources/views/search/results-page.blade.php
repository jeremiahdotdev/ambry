<x-blank-page
    :title="'Search Results | '.config('app.name', 'Ambry')"
    page-class="search-results-page"
    :assets="[
        'resources/css/search/results.css',
    ]"
>
    <livewire:search :results-page="true" />
    <x-slot:circles>
        <div class="search-circle search-results-circle-marigold" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-plum" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-moss" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-rose" aria-hidden="true"></div>
        <div class="search-circle search-results-circle-cream" aria-hidden="true"></div>
    </x-slot:circles>
</x-blank-page>
