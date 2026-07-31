@php
    use Illuminate\Support\Str;

    $temperaments = is_array($saint->profile_temperaments ?? null) ? $saint->profile_temperaments : [];
    $vices = collect($saint->profile_key_struggles ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $virtues = collect($saint->profile_key_virtues ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $feastDays = collect($saint->profile_feast_days ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $relatedSaints = collect($saint->profile_related_saints ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $works = collect($saint->profile_works ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $landmarks = collect($saint->profile_landmarks ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $sources = collect($saint->profile_sources ?? [])->filter(fn ($item) => is_array($item) && filled($item['title'] ?? null));
    $researchNotes = collect($saint->profile_research_notes ?? [])->filter();
    $temperamentScores = collect($temperaments['scores'] ?? [])
        ->filter(fn ($score) => is_numeric($score))
        ->map(fn ($score) => max(0, (float) $score));
    $temperamentMax = $temperamentScores->max();
    $temperamentScale = $temperamentMax > 0 ? 75 / $temperamentMax : 0;
    $temperamentDisplayScores = $temperamentScores
        ->map(fn ($score) => max(0, min(75, (int) round($score * $temperamentScale))));
    $monthNames = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];
    $formatFeastDate = fn (array $recurrence): ?string => filled($recurrence['month'] ?? null) && filled($recurrence['day'] ?? null)
        ? ($monthNames[(int) $recurrence['month']] ?? (string) $recurrence['month']).' '.(int) $recurrence['day']
        : null;
    $hasProfile = $temperamentScores->isNotEmpty()
        || $vices->isNotEmpty()
        || $virtues->isNotEmpty()
        || $feastDays->isNotEmpty()
        || $relatedSaints->isNotEmpty()
        || $works->isNotEmpty()
        || $landmarks->isNotEmpty()
        || $sources->isNotEmpty()
        || $researchNotes->isNotEmpty();
    $sourceIds = fn ($item) => collect($item['source_ids'] ?? [])
        ->filter()
        ->map(fn ($sourceId) => '<span>'.e($sourceId).'</span>')
        ->implode('');
    $formatCitation = function (array $source): string {
        $isNewAdvent = ($source['source_type'] ?? null) === 'new_advent'
            || str_contains((string) ($source['url'] ?? ''), 'newadvent.org');
        $publisherOrAuthor = trim((string) ($source['publisher_or_author'] ?? ''));
        $title = trim((string) ($source['title'] ?? ''));
        $accessed = filled($source['accessed_date'] ?? null)
            ? 'Accessed '.$source['accessed_date']
            : 'Accessed '.now()->year;
        $parts = collect([
            filled($publisherOrAuthor) ? rtrim($publisherOrAuthor, '.') : null,
            filled($title) ? '“'.rtrim($title, '.').'.”' : null,
            $isNewAdvent ? 'New Advent' : null,
            $accessed,
        ])->filter()->implode('. ');

        return preg_replace('/\.”\.\s+/', '.” ', Str::finish($parts, '.')) ?? Str::finish($parts, '.');
    };
@endphp

@if ($hasProfile)
    <section class="saint-profile-enrichment" aria-label="Profile details">
        @if ($temperamentScores->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Temperament</h2>
                <div class="saint-temperament-scores">
                    @foreach ($temperamentDisplayScores as $name => $scoreValue)
                        <div class="saint-temperament-score">
                            <span>{{ Str::of((string) $name)->replace('_', ' ')->title() }}</span>
                            <i><b style="inline-size: {{ $scoreValue }}%;"></b></i>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($virtues->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Virtues</h2>
                @foreach ($virtues as $virtue)
                    <article class="saint-profile-detail">
                        <h4>{{ Str::of((string) $virtue['name'])->replace('_', ' ')->title() }}</h4>
                        @if (filled($virtue['summary'] ?? null))
                            <p>{{ $virtue['summary'] }}</p>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        @if ($vices->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Vices</h2>
                @foreach ($vices as $vice)
                    <article class="saint-profile-detail">
                        <h4>{{ Str::of((string) $vice['name'])->replace('_', ' ')->title() }}</h4>
                        @if (filled($vice['summary'] ?? null))
                            <p>{{ $vice['summary'] }}</p>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif
        @if ($hasProfile)
            <section class="saint-profile-block">
                <h2>Feast Days</h2>
                @if ($feastDays->isEmpty())
                    <p class="saint-profile-empty">None returned.</p>
                @else
                    <ul class="saint-profile-list">
                        @foreach ($feastDays as $feastDay)
                            @php($recurrence = is_array($feastDay['recurrence'] ?? null) ? $feastDay['recurrence'] : [])
                            @php($feastDate = $formatFeastDate($recurrence))
                            <li>
                                <strong>{{ $feastDay['name'] }}</strong>
                                @if (filled($feastDate))
                                    <span>{{ $feastDate }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($relatedSaints->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Connections</h2>
                <div class="saint-profile-chip-list">
                    @foreach ($relatedSaints as $relatedSaint)
                        @if (filled($relatedSaint['slug'] ?? null))
                            <a href="{{ route('saints.profile', ['saint' => $relatedSaint['slug']]) }}">{{ $relatedSaint['name'] }}</a>
                        @else
                            <a href="{{ route('search.results', ['q' => $relatedSaint['name']]) }}">{{ $relatedSaint['name'] }}</a>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if ($works->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Works</h2>
                @foreach ($works as $work)
                    <article class="saint-profile-detail">
                        <h4>{{ $work['name'] }}</h4>
                        @if (filled($work['description'] ?? null))
                            <p>{{ $work['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        @if ($landmarks->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Landmarks</h2>
                @foreach ($landmarks as $landmark)
                    <article class="saint-profile-detail">
                        <h4>{{ $landmark['name'] }}</h4>
                        @if (filled($landmark['location'] ?? null))
                            <p class="saint-profile-location">{{ $landmark['location'] }}</p>
                        @endif
                        @if (filled($landmark['description'] ?? null))
                            <p>{{ $landmark['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        @if ($researchNotes->isNotEmpty())
            <section class="saint-profile-block saint-profile-sources">
                <details>
                    <summary><h2>Research Notes</h2></summary>
                    <ul>
                        @foreach ($researchNotes as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                </details>
            </section>
        @endif

        @if ($sources->isNotEmpty())
            <section class="saint-profile-block saint-profile-sources">
                <details>
                    <summary><h2>Sources</h2></summary>
                    @foreach ($sources as $source)
                        @if (filled($source['url'] ?? null))
                            <p class="saint-profile-citation">
                                <a href="{{ $source['url'] }}" target="_blank" rel="noreferrer">{{ $formatCitation($source) }}</a>
                            </p>
                        @else
                            <p class="saint-profile-citation">{{ $formatCitation($source) }}</p>
                        @endif
                    @endforeach
                </details>
            </section>
        @endif
    </section>
@endif
