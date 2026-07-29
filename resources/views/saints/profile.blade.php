@php
    use App\Support\SaintPageVariants;

    $subtitle ??= null;
    $variant ??= 'classic';
    $hasCustomImage = file_exists(public_path("saints/{$saint->slug}.png"));
    $variants = SaintPageVariants::all($saint->slug);
    $layout = $variants[$variant] ?? $variants[SaintPageVariants::defaultForSlug($saint->slug)];
    $scheme = $layout['scheme'];
@endphp

<main
    class="saint-page"
    data-saint-variant="{{ $variant }}"
    style="--saint-page-start: {{ $scheme['pageStart'] }}; --saint-page-mid: {{ $scheme['pageMid'] }}; --saint-page-end: {{ $scheme['pageEnd'] }}; --saint-card: {{ $scheme['card'] }}; --saint-border: {{ $scheme['border'] }}; --saint-text: {{ $scheme['text'] }}; --saint-accent: {{ $scheme['accent'] }}; --saint-backdrop: {{ $scheme['backdrop'] }};"
>
    @include('shared.back-to-search', ['class' => 'saint-backlink'])

    @foreach ($layout['circles'] as $circle)
        @php
            if (($layout['randomizeScheme'] ?? false) && ! $hasCustomImage) {
                $circle['first'] = $scheme['circles'][$loop->index % count($scheme['circles'])];
                $circle['second'] = $scheme['circles'][($loop->index + 1) % count($scheme['circles'])];
            }
        @endphp
        @include('shared.circles.bisected', [
            'size' => $circle['size'],
            'cssSize' => $circle['cssSize'],
            'x' => $circle['x'],
            'y' => $circle['y'],
            'first' => $circle['first'],
            'second' => $circle['second'],
            'rotation' => $circle['rotation'],
            'opacity' => $circle['opacity'] ?? 0.5,
            'cut' => $circle['cut'] ?? 'straight',
        ])
    @endforeach

    <section class="saint-hero" aria-label="{{ $saint->displayName() }}">
        <div class="saint-art">
            @include('saints.image-block', ['saint' => $saint, 'slug' => $saint->slug, 'name' => $saint->displayName(), 'gender' => $saint->gender])
        </div>

        @include('saints.copy-panel', ['saint' => $saint, 'subtitle' => $subtitle])
    </section>
</main>
