<?php

namespace App\Http\Controllers;

use App\Services\SaintSearchService;
use App\Support\SearchFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SaintSearchService $saintSearch,
        private readonly SearchFilters $filters,
    ) {}

    public function index(): View
    {
        return view('search.index');
    }

    public function search(): View
    {
        return view('search.results-page');
    }

    public function suggestions(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:200', 'type' => 'nullable|string', 'popular' => 'nullable|string']);
        [$query, $type] = $this->filters->normalizedQueryAndType((string) $request->query('q'), (string) $request->query('type', 'saint'));

        if (mb_strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $results = $this->saintSearch->search(
            $query, type: $type, popular: $this->filters->selectedPopularSearch((string) $request->query('popular', '')), with: [], limit: 6,
        );

        return response()->json(['suggestions' => $results->map(fn ($saint) => [
            'name' => $saint->displayName(),
            'type' => SearchFilters::SEARCH_TYPES[$saint->canonical_status] ?? 'Saint',
            'url' => route('saints.profile', $saint),
        ])]);
    }
}
