<?php

namespace App\Http\Controllers;

use App\Services\SaintSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const SEARCH_TYPES = [
        'saint' => 'Saint',
        'pope' => 'Pope',
        'blessed' => 'Blessed',
        'venerable' => 'Venerable',
        'church_father' => 'Church Father',
    ];

    public function __construct(
        private readonly SaintSearchService $saintSearch,
    ) {}

    public function index(Request $request): View
    {
        $selectedType = $this->selectedType($request);

        return view('search.index', [
            'query' => '',
            'results' => [],
            'searched' => false,
            'error' => null,
            'selectedType' => $selectedType,
            'searchTypes' => self::SEARCH_TYPES,
        ]);
    }

    public function search(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $selectedType = $this->selectedType($request);

        if ($query === '') {
            return view('search.index', [
                'query' => $query,
                'results' => [],
                'searched' => false,
                'error' => 'Enter a search term.',
                'selectedType' => $selectedType,
                'searchTypes' => self::SEARCH_TYPES,
            ]);
        }

        return view('search.index', [
            'query' => $query,
            'results' => $this->saintSearch->search($query, type: $selectedType),
            'searched' => true,
            'error' => null,
            'selectedType' => $selectedType,
            'searchTypes' => self::SEARCH_TYPES,
        ]);
    }

    private function selectedType(Request $request): string
    {
        $selectedType = (string) $request->query('type', 'saint');

        return array_key_exists($selectedType, self::SEARCH_TYPES) ? $selectedType : 'saint';
    }
}
