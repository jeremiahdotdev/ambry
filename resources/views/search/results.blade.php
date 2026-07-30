<section class="search-results" aria-label="Saint search results">
    @forelse ($results as $saint)
        @php
            $generatedImageUrl = $saint->image_thumb_url ?? \App\Support\GeneratedSaintImages::url($saint->slug, 'thumb');
            $relativePath = "saints/{$saint->slug}.png";
            $hasImage = file_exists(public_path($relativePath));
            $fallbackPath = $saint->gender === 'female' ? 'saints/default_female.png' : 'saints/default.png';
            $imageUrl = $generatedImageUrl ?? asset($hasImage ? $relativePath : $fallbackPath);
            $displayName = $saint->displayName();
            $biography = $saint->displayBiography();
            $lifeDates = $saint->displayLifeDates();
        @endphp

        <article class="search-result">
            <a class="search-result-link" href="{{ route('saints.profile', $saint) }}">
                <span class="search-result-image-wrapper">
                    <img class="search-result-image" src="{{ $imageUrl }}" alt="">
                </span>
                <span class="search-result-content">
                    <span class="search-result-title">
                        <span>{{ $searchTypes[$saint->canonical_status] ?? ucfirst(str_replace('_', ' ', $saint->canonical_status)) }}</span>
                        {{ $displayName }}
                    </span>
                    <span class="search-result-meta">
                        @if ($lifeDates)
                            <span>{{ $lifeDates }}</span>
                        @endif
                    </span>
                    @if ($biography)
                    <span class="search-result-excerpt">
                        <span class="search-result-excerpt-text">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::of($biography)->squish(), 250) }}</span>
                        <span class="search-result-excerpt-overlay"></span>
                    </span>
                    @endif
                    @if ($saint->patronages->isNotEmpty())
                        <span class="search-result-patronages" aria-label="Patronages">
                            @foreach ($saint->patronages->take(3) as $patronage)
                                <span>{{ $patronage->name }}</span>
                            @endforeach
                        </span>
                    @endif
                </span>
            </a>
        </article>
    @empty
        <p class="search-empty">No {{ strtolower($selectedPopularLabel ?? $selectedTypePlural) }} matched.</p>
    @endforelse
</section>

@if (method_exists($results, 'hasPages') && $results->hasPages())
    <nav class="search-pagination" aria-label="Search results pagination">
        @if ($results->onFirstPage())
            <span class="search-pagination-link is-disabled" aria-disabled="true">
                <span aria-hidden="true">←</span>
                <span>1</span>
            </span>
        @else
            <a class="search-pagination-link" href="{{ $results->previousPageUrl() }}" rel="prev" aria-label="Previous page, page {{ $results->currentPage() - 1 }}">
                <span aria-hidden="true">←</span>
                <span>{{ $results->currentPage() - 1 }}</span>
            </a>
        @endif

        <span class="search-pagination-page is-current" aria-current="page">{{ $results->currentPage() }}</span>

        @if ($results->hasMorePages())
            <a class="search-pagination-link" href="{{ $results->nextPageUrl() }}" rel="next" aria-label="Next page, page {{ $results->currentPage() + 1 }}">
                <span>{{ $results->currentPage() + 1 }}</span>
                <span aria-hidden="true">→</span>
            </a>
        @else
            <span class="search-pagination-link is-disabled" aria-disabled="true">
                <span>{{ $results->currentPage() }}</span>
                <span aria-hidden="true">→</span>
            </span>
        @endif
    </nav>
@endif
