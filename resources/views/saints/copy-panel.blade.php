<div class="saint-copy-panel">
    @include('saints.title-block', [
        'name' => $saint->primary_name,
        'subtitle' => $subtitle ?? null,
        'lifeDates' => $saint->life_dates,
    ])

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
