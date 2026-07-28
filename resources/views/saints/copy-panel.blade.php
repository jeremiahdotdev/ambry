<div class="saint-copy-panel">
    @include('saints.title-block', [
        'name' => $saint->primary_name,
        'subtitle' => $subtitle ?? null,
        'lifeDates' => $saint->displayLifeDates(),
    ])

    @if ($saint->patronages->isNotEmpty())
        <section class="saint-patronages" aria-labelledby="saint-patronages-title">
            <h2 id="saint-patronages-title">Patronages</h2>
            <ul>
                @foreach ($saint->patronages as $patronage)
                    <li>
                        <a href="{{ route('search.results', ['q' => $patronage->name]) }}">{{ $patronage->name }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <span class="saint-divider"></span>

    @if ($saint->biography)
        <div class="saint-intro">
            @foreach (preg_split('/\R{2,}/', \Illuminate\Support\Str::of($saint->biography)->stripTags()->toString()) as $paragraph)
                @if (trim($paragraph) !== '')
                    <p>{{ trim($paragraph) }}</p>
                @endif
            @endforeach
        </div>
    @endif
</div>
