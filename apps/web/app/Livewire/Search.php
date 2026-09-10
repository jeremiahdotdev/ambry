<?php

namespace App\Livewire;

use App\Services\SaintSearchService;
use App\Support\SearchFilters;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Search extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'type', history: true, except: 'saint')]
    public string $type = 'saint';

    #[Url(history: true, except: '')]
    public string $popular = '';

    #[Locked]
    public bool $resultsPage = false;

    public function mount(bool $resultsPage = false): void
    {
        $this->resultsPage = $resultsPage;
    }

    public function updated($property): void
    {
        if (in_array($property, ['query', 'type', 'popular'], true)) {
            $this->resetPage();
        }
    }

    public function togglePopular(string $value): void
    {
        if (array_key_exists($value, SearchFilters::POPULAR_SEARCHES)) {
            $this->popular = $this->popular === $value ? '' : $value;
            $this->resetPage();
        }
    }

    public function search(): void
    {
        $this->validate(['query' => 'nullable|string|max:200']);
        $this->redirectRoute('search.results', [
            'q' => $this->query, 'type' => $this->type, 'popular' => $this->popular,
        ], navigate: true);
    }

    public function render()
    {
        $filters = app(SearchFilters::class);
        [$query, $selectedType] = $filters->normalizedQueryAndType(mb_substr($this->query, 0, 200), $this->type);
        $selectedPopularSearch = $filters->selectedPopularSearch($this->popular);
        $search = app(SaintSearchService::class);
        $results = $this->resultsPage
            ? $search->search($query, type: $selectedType, popular: $selectedPopularSearch, perPage: 10, with: ['patronages'])
                ->withPath(route('search.results'))->appends(array_filter(['q' => $this->query, 'type' => $this->type, 'popular' => $this->popular], fn ($value) => $value !== ''))
            : collect();

        return view($this->resultsPage ? 'livewire.search-results' : 'livewire.search', [
            'query' => $this->query,
            'selectedType' => $selectedType,
            'selectedPopularSearch' => $selectedPopularSearch,
            'searchTypes' => SearchFilters::SEARCH_TYPES,
            'popularSearches' => SearchFilters::POPULAR_SEARCHES,
            'searched' => $this->resultsPage,
            'error' => null,
            'results' => $results,
        ]);
    }
}
