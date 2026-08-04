@php
    $temperamentScores = $saint->displayProfileTemperamentScores();
    $virtues = $saint->displayProfileVirtues();
    $vices = $saint->displayProfileVices();
    $feastDays = $saint->displayProfileFeastDays();
    $relatedSaints = $saint->displayProfileRelatedSaints();
    $works = $saint->displayProfileWorks();
    $landmarks = $saint->displayProfileLandmarks();
    $sources = $saint->displayProfileSources();
    $researchNotes = $saint->displayProfileResearchNotes();
    $hasProfile = $saint->hasProfileEnrichment();
@endphp

@if ($hasProfile)
    <section class="saint-profile-enrichment" aria-label="Profile details">
        @if (! empty($temperamentScores))
            <section class="saint-profile-block">
                <h2>Temperament</h2>
                <div class="saint-temperament-scores">
                    @foreach ($temperamentScores as $temperament)
                        <div class="saint-temperament-score">
                            <span>{{ $temperament['label'] }}</span>
                            <i><b style="inline-size: {{ $temperament['score'] }}%;"></b></i>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($virtues))
            <section class="saint-profile-block">
                <h2>Virtues</h2>
                @foreach ($virtues as $virtue)
                    <article class="saint-profile-detail">
                        <h4>{{ $virtue['name'] }}</h4>
                        @if (filled($virtue['summary'] ?? null))
                            <p>{{ $virtue['summary'] }}</p>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        @if (! empty($vices))
            <section class="saint-profile-block">
                <h2>Vices</h2>
                @foreach ($vices as $vice)
                    <article class="saint-profile-detail">
                        <h4>{{ $vice['name'] }}</h4>
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
                @if (empty($feastDays))
                    <p class="saint-profile-empty">None returned.</p>
                @else
                    <ul class="saint-profile-list">
                        @foreach ($feastDays as $feastDay)
                            <li>
                                <strong>{{ $feastDay['name'] }}</strong>
                                @if (filled($feastDay['date'] ?? null))
                                    <span>{{ $feastDay['date'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if (! empty($relatedSaints))
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

        @if (! empty($works))
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

        @if (! empty($landmarks))
            <section class="saint-profile-block">
                <h2>Landmarks</h2>
                @foreach ($landmarks as $landmark)
                    <article class="saint-profile-detail saint-profile-detail--landmark">
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

        @if (! empty($researchNotes))
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

        @if (! empty($sources))
            <section class="saint-profile-block saint-profile-sources">
                <details>
                    <summary><h2>Sources</h2></summary>
                    @foreach ($sources as $source)
                        @if (filled($source['url'] ?? null))
                            <p class="saint-profile-citation">
                                <a href="{{ $source['url'] }}" target="_blank" rel="noreferrer">{{ $source['citation'] }}</a>
                            </p>
                        @else
                            <p class="saint-profile-citation">{{ $source['citation'] }}</p>
                        @endif
                    @endforeach
                </details>
            </section>
        @endif
    </section>
@endif
