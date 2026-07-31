@php
    use Illuminate\Support\Str;

    $temperaments = is_array($saint->profile_temperaments ?? null) ? $saint->profile_temperaments : [];
    $vices = collect($saint->profile_key_struggles ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $virtues = collect($saint->profile_key_virtues ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $feastDays = collect($saint->profile_feast_days ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $relatedSaints = collect($saint->profile_related_saints ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $works = collect($saint->profile_works ?? [])->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null));
    $sources = collect($saint->profile_sources ?? [])->filter(fn ($item) => is_array($item) && filled($item['title'] ?? null));
    $researchNotes = collect($saint->profile_research_notes ?? [])->filter();
    $temperamentScores = collect($temperaments['scores'] ?? [])->filter(fn ($score) => is_numeric($score));
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
        || $sources->isNotEmpty()
        || $researchNotes->isNotEmpty();
    $sourceIds = fn ($item) => collect($item['source_ids'] ?? [])
        ->filter()
        ->map(fn ($sourceId) => '<span>'.e($sourceId).'</span>')
        ->implode('');
@endphp

@if ($hasProfile)
    <section class="saint-profile-enrichment" aria-label="Profile details">
        @if ($temperamentScores->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Temperament</h2>
                <p class="saint-profile-meta">
                    @if (filled($temperaments['primary'] ?? null))
                        <span>Primary: {{ $temperaments['primary'] }}</span>
                    @endif
                    @if (filled($temperaments['secondary'] ?? null))
                        <span>Secondary: {{ $temperaments['secondary'] }}</span>
                    @endif
                </p>
                <div class="saint-temperament-scores">
                    @foreach ($temperamentScores as $name => $score)
                        @php($scoreValue = max(0, min(100, (int) $score)))
                        <div class="saint-temperament-score">
                            <span>{{ Str::of((string) $name)->replace('_', ' ')->title() }}</span>
                            <i><b style="inline-size: {{ $scoreValue }}%;"></b></i>
                            <strong>{{ $scoreValue }}</strong>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($vices->isNotEmpty() || $virtues->isNotEmpty())
            <section class="saint-profile-block">
                <h2>Interior Life</h2>
                <div class="saint-profile-two-column">
                    @if ($vices->isNotEmpty())
                        <div>
                            <h3>Vices</h3>
                            @foreach ($vices as $vice)
                                <article class="saint-profile-detail">
                                    <h4>{{ Str::of((string) $vice['name'])->replace('_', ' ')->title() }}</h4>
                                    @if (filled($vice['summary'] ?? null))
                                        <p>{{ $vice['summary'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if ($virtues->isNotEmpty())
                        <div>
                            <h3>Virtues</h3>
                            @foreach ($virtues as $virtue)
                                <article class="saint-profile-detail">
                                    <h4>{{ Str::of((string) $virtue['name'])->replace('_', ' ')->title() }}</h4>
                                    @if (filled($virtue['summary'] ?? null))
                                        <p>{{ $virtue['summary'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
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

        @if ($sources->isNotEmpty() || $researchNotes->isNotEmpty())
            <section class="saint-profile-block saint-profile-sources">
                <h2>Profile Sources</h2>
                @foreach ($sources as $source)
                    @if (filled($source['url'] ?? null))
                        <p><a href="{{ $source['url'] }}" target="_blank" rel="noreferrer">{{ $source['title'] }}</a></p>
                    @else
                        <p>{{ $source['title'] }}</p>
                    @endif
                @endforeach

                @if ($researchNotes->isNotEmpty())
                    <details>
                        <summary>Research notes</summary>
                        <ul>
                            @foreach ($researchNotes as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        @endif
    </section>
@endif
