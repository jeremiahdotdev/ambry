<?php

namespace App\Http\Controllers;

use App\Services\SaintSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const RESULTS_PER_PAGE = 10;

    private const SEARCH_TYPES = [
        'saint' => 'Saint',
        'pope' => 'Pope',
        'blessed' => 'Blessed',
        'venerable' => 'Venerable',
    ];

    private const POPULAR_SEARCHES = [
        'patron_saints' => [
            'label' => 'Patron Saints',
            'icon' => 'shield-check',
        ],
        'martyrs' => [
            'label' => 'Martyrs',
            'icon' => 'flame',
        ],
        'men' => [
            'label' => 'Men',
            'icon' => 'mars',
        ],
        'women' => [
            'label' => 'Women',
            'icon' => 'venus',
        ],
        'doctors' => [
            'label' => 'Doctors',
            'icon' => 'graduation-cap',
        ],
    ];

    public function __construct(
        private readonly SaintSearchService $saintSearch,
    ) {}

    public function index(Request $request): View
    {
        $selectedType = $this->selectedType($request);
        $selectedPopularSearch = $this->selectedPopularSearch($request);

        return view('search.index', [
            'query' => '',
            'results' => [],
            'searched' => false,
            'error' => null,
            'selectedType' => $selectedType,
            'searchTypes' => self::SEARCH_TYPES,
            'popularSearches' => self::POPULAR_SEARCHES,
            'selectedPopularSearch' => $selectedPopularSearch,
        ]);
    }

    public function search(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $selectedType = $this->selectedType($request);
        $selectedPopularSearch = $this->selectedPopularSearch($request);

        return view('search.results-page', [
            'query' => $query,
            'results' => $this->saintSearch
                ->search($query, type: $selectedType, popular: $selectedPopularSearch, perPage: self::RESULTS_PER_PAGE, with: ['patronages'])
                ->withQueryString(),
            'searched' => true,
            'error' => null,
            'selectedType' => $selectedType,
            'searchTypes' => self::SEARCH_TYPES,
            'popularSearches' => self::POPULAR_SEARCHES,
            'selectedPopularSearch' => $selectedPopularSearch,
        ]);
    }

    private function selectedType(Request $request): string
    {
        $rawType = (string) $request->query('type', 'saint');
        $selectedType = array_key_exists($rawType, self::SEARCH_TYPES) ? $rawType : 'saint';

        logger()->debug('Search selected type', [
            'raw_type' => $rawType,
            'selected_type' => $selectedType,
            'query' => $request->query('q'),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
        ]);

        return $selectedType;
    }

    private function selectedPopularSearch(Request $request): ?string
    {
        $popularSearch = (string) $request->query('popular', '');
        $popularSearch = $popularSearch === 'patrons' ? 'patron_saints' : $popularSearch;

        return array_key_exists($popularSearch, self::POPULAR_SEARCHES) ? $popularSearch : null;
    }
}
