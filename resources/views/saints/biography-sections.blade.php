@php
    $sections = collect($saint->biography_sections ?? [])
        ->filter(fn ($section) => is_array($section) && (
            filled($section['body'] ?? null)
            || (($section['kind'] ?? null) === 'sources' && filled($section['pageSource']['url'] ?? null))
        ));
    $documentSources = collect($saint->biography_sources ?? [])
        ->filter(fn ($source) => is_array($source) && filled($source['url'] ?? null));
@endphp

@if ($sections->isNotEmpty())
    <div class="saint-intro saint-biography-sections">
        @foreach ($sections as $section)
            <section class="saint-biography-section {{ ($section['kind'] ?? 'body') === 'sources' ? 'saint-biography-section--sources' : '' }}">
                @php
                    $kind = $section['kind'] ?? 'body';
                    $body = (string) ($section['body_html'] ?? $section['body'] ?? '');
                    $bodyIsHtml = filled($section['body_html'] ?? null);
                    $usesSourceEntries = false;
                    $displayParagraphs = $kind === 'sources'
                        ? []
                        : ($usesSourceEntries
                        ? $section['source_entries']
                        : preg_split('/\R{2,}/', $body));
                    $renderHtml = $bodyIsHtml && ! $usesSourceEntries;
                    $sectionHeading = trim((string) ($section['heading'] ?? ''));
                    $normalizeHeading = fn ($value) => strtolower(preg_replace(
                        '/\s+/',
                        ' ',
                        trim(preg_replace('/^(saint|st\.?|pope)\s+/i', '', (string) $value))
                    ));
                    $displayHeading = $sectionHeading;

                    if ($loop->first && $kind === 'body') {
                        $isNameHeading = filled($sectionHeading)
                            && $normalizeHeading($sectionHeading) === $normalizeHeading($saint->primary_name);
                        $displayHeading = blank($sectionHeading) || $isNameHeading ? 'About' : $sectionHeading;
                    }
                @endphp

                @if (filled($displayHeading))
                    <h2>{{ $displayHeading }}</h2>
                @endif

                @foreach ($displayParagraphs ?: [] as $paragraph)
                    @php
                        $paragraphText = is_array($paragraph) ? (string) ($paragraph['text'] ?? '') : (string) $paragraph;
                        $paragraphHtml = is_array($paragraph) ? (string) ($paragraph['html'] ?? $paragraphText) : (string) $paragraph;
                        $renderedParagraph = ($renderHtml || $usesSourceEntries) ? $paragraphHtml : $paragraphText;
                    @endphp

                    @if (filled(trim($paragraphText)))
                        <p>
                            @foreach (preg_split('/(\[source:\d+\])/', trim($renderedParagraph), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part)
                                @if (! preg_match('/^\[source:\d+\]$/', $part))
                                    @if ($renderHtml || $usesSourceEntries)
                                        {!! $part !!}
                                    @else
                                        {{ $part }}
                                    @endif
                                @endif
                            @endforeach
                        </p>
                    @endif
                @endforeach

                @if ($kind === 'sources')
                    @if (filled($section['pageSource']['url'] ?? null))
                        <p class="saint-source-document">
                            <a href="{{ $section['pageSource']['url'] }}" target="_blank" rel="noreferrer">
                                {{ $section['pageSource']['title'] ?? 'New Advent' }}
                                @if (filled($section['pageSource']['article'] ?? null))
                                    <span>{{ $section['pageSource']['article'] }}</span>
                                @endif
                                <span>retrieved {{ now()->year }}</span>
                            </a>
                        </p>
                    @endif

                    @if (blank($section['pageSource']['url'] ?? null))
                        @foreach ($documentSources as $source)
                            <p class="saint-source-document">
                                <a href="{{ $source['url'] }}" target="_blank" rel="noreferrer">
                                    {{ $source['title'] ?? 'Source document' }}
                                    @if (filled($source['locator'] ?? null))
                                        <span>{{ $source['locator'] }}</span>
                                    @endif
                                    <span>retrieved {{ now()->year }}</span>
                                </a>
                            </p>
                        @endforeach
                    @endif
                @endif
            </section>
        @endforeach
    </div>
@endif
