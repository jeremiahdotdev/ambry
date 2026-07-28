@php
    $subtitle ??= null;
    $variant ??= 'classic';
    $hasCustomImage = file_exists(public_path("saints/{$saint->slug}.png"));
    $schemes = [
        [
            'pageStart' => '#d9e5df',
            'pageMid' => '#f1f4ec',
            'pageEnd' => '#fbfaf4',
            'card' => '#f7f8ee',
            'border' => '#c9d4ca',
            'text' => '#143433',
            'accent' => '#4e7b68',
            'backdrop' => 'rgb(247 248 238 / 72%)',
            'circles' => ['#315a37', '#d9e5df', '#1f4c5f', '#a8beb1', '#f1f4ec'],
        ],
        [
            'pageStart' => '#dfe6ef',
            'pageMid' => '#f3f6f8',
            'pageEnd' => '#fbfbf7',
            'card' => '#f8f9f2',
            'border' => '#c9d2dc',
            'text' => '#183348',
            'accent' => '#56738f',
            'backdrop' => 'rgb(248 249 242 / 72%)',
            'circles' => ['#28465f', '#dfe6ef', '#7d8ea2', '#b8c7d5', '#f3f6f8'],
        ],
        [
            'pageStart' => '#e7dedc',
            'pageMid' => '#f6f1ee',
            'pageEnd' => '#fbfaf6',
            'card' => '#fbf6ec',
            'border' => '#d9c9c2',
            'text' => '#322d2b',
            'accent' => '#8b5e5a',
            'backdrop' => 'rgb(251 246 236 / 72%)',
            'circles' => ['#7d2e2b', '#e7dedc', '#665f55', '#c9aaa1', '#f6f1ee'],
        ],
        [
            'pageStart' => '#dde5d1',
            'pageMid' => '#f1f5e9',
            'pageEnd' => '#fbfaf3',
            'card' => '#f7f8ed',
            'border' => '#cbd8bf',
            'text' => '#243829',
            'accent' => '#6f805c',
            'backdrop' => 'rgb(247 248 237 / 72%)',
            'circles' => ['#4f6844', '#dde5d1', '#7c9070', '#bdcba9', '#f1f5e9'],
        ],
    ];
    $defaultScheme = $schemes[abs(crc32($saint->slug)) % count($schemes)];
    $variants = [
        'default' => [
            'randomizeScheme' => true,
            'scheme' => $defaultScheme,
            'circles' => [
                ['size' => 410, 'cssSize' => 'clamp(28rem, 52vmin, 58rem)', 'x' => '-180px', 'y' => '-180px', 'first' => '#315a37', 'second' => '#d9e5df', 'rotation' => 12, 'opacity' => 0.42, 'cut' => 'curved'],
                ['size' => 350, 'cssSize' => 'clamp(22rem, 40vmin, 46rem)', 'x' => 'calc(100% - 210px)', 'y' => '-86px', 'first' => '#1f4c5f', 'second' => '#f1f4ec', 'rotation' => -28, 'opacity' => 0.38],
                ['size' => 410, 'cssSize' => 'clamp(26rem, 48vmin, 54rem)', 'x' => 'calc(100% - 330px)', 'y' => 'calc(100% - 120px)', 'first' => '#315a37', 'second' => '#d9e5df', 'rotation' => 48, 'opacity' => 0.48, 'cut' => 'curved'],
                ['size' => 280, 'cssSize' => 'clamp(18rem, 34vmin, 38rem)', 'x' => '-90px', 'y' => 'calc(100% - 150px)', 'first' => '#a8beb1', 'second' => '#f1f4ec', 'rotation' => -20, 'opacity' => 0.44],
            ],
        ],
        'classic' => [
            'scheme' => [
                'pageStart' => '#e3d1a4',
                'pageMid' => '#f8f1e6',
                'pageEnd' => '#fffaf0',
                'card' => '#fff6e0',
                'border' => '#ded3c1',
                'text' => '#102b2d',
                'accent' => '#ad761e',
                'backdrop' => 'rgb(255 253 248 / 68%)',
            ],
            'circles' => [
                ['size' => 410, 'cssSize' => 'clamp(24rem, 44vmin, 48rem)', 'x' => '-150px', 'y' => '-160px', 'first' => '#b9831f', 'second' => '#efe1bd', 'rotation' => -1],
                ['size' => 210, 'cssSize' => 'clamp(14rem, 24vmin, 28rem)', 'x' => 'calc(100% - 86px)', 'y' => '-76px', 'first' => '#d5a139', 'second' => '#f2e7d0', 'rotation' => -18, 'opacity' => 0.42],
                ['size' => 410, 'cssSize' => 'clamp(24rem, 44vmin, 48rem)', 'x' => 'calc(100% - 260px)', 'y' => 'calc(100% - 90px)', 'first' => '#113d2a', 'second' => '#f3ead7', 'rotation' => 42, 'opacity' => 0.52],
                ['size' => 350, 'cssSize' => 'clamp(21rem, 38vmin, 42rem)', 'x' => '-78px', 'y' => 'calc(100% - 120px)', 'first' => '#315a37', 'second' => '#c9972d', 'rotation' => -22, 'opacity' => 0.48],
            ],
        ],
        'rounded' => [
            'scheme' => [
                'pageStart' => '#dfe6ef',
                'pageMid' => '#f3f6f8',
                'pageEnd' => '#fbfbf7',
                'card' => '#f8f9f2',
                'border' => '#c9d2dc',
                'text' => '#183348',
                'accent' => '#56738f',
                'backdrop' => 'rgb(248 249 242 / 72%)',
            ],
            'circles' => [
                ['size' => 410, 'cssSize' => 'clamp(25rem, 46vmin, 50rem)', 'x' => '-170px', 'y' => '-150px', 'first' => '#28465f', 'second' => '#dfe6ef', 'rotation' => 8],
                ['size' => 350, 'cssSize' => 'clamp(20rem, 36vmin, 42rem)', 'x' => 'calc(100% - 220px)', 'y' => 'calc(100% - 110px)', 'first' => '#7d8ea2', 'second' => '#f3f6f8', 'rotation' => 28, 'opacity' => 0.5],
                ['size' => 280, 'cssSize' => 'clamp(16rem, 30vmin, 34rem)', 'x' => '80px', 'y' => '12svh', 'first' => '#b8c7d5', 'second' => '#fbfbf7', 'rotation' => 90, 'opacity' => 0.42],
            ],
        ],
        'curvy' => [
            'scheme' => [
                'pageStart' => '#d9e5df',
                'pageMid' => '#f1f4ec',
                'pageEnd' => '#fbfaf4',
                'card' => '#f7f8ee',
                'border' => '#c9d4ca',
                'text' => '#143433',
                'accent' => '#4e7b68',
                'backdrop' => 'rgb(247 248 238 / 72%)',
            ],
            'circles' => [
                ['size' => 410, 'cssSize' => 'clamp(34rem, 62vmin, 68rem)', 'x' => '-260px', 'y' => '-260px', 'first' => '#315a37', 'second' => '#d9e5df', 'rotation' => 24, 'opacity' => 0.42],
                ['size' => 350, 'cssSize' => 'clamp(24rem, 46vmin, 54rem)', 'x' => 'calc(100% - 170px)', 'y' => '6svh', 'first' => '#1f4c5f', 'second' => '#f1f4ec', 'rotation' => -38, 'opacity' => 0.45],
                ['size' => 410, 'cssSize' => 'clamp(30rem, 56vmin, 62rem)', 'x' => 'calc(100% - 360px)', 'y' => 'calc(100% - 120px)', 'first' => '#4e7b68', 'second' => '#fbfaf4', 'rotation' => 68, 'opacity' => 0.5],
            ],
        ],
        'curved-cuts' => [
            'scheme' => [
                'pageStart' => '#e7dedc',
                'pageMid' => '#f6f1ee',
                'pageEnd' => '#fbfaf6',
                'card' => '#fbf6ec',
                'border' => '#d9c9c2',
                'text' => '#322d2b',
                'accent' => '#8b5e5a',
                'backdrop' => 'rgb(251 246 236 / 72%)',
            ],
            'circles' => [
                ['size' => 410, 'cssSize' => 'clamp(28rem, 52vmin, 58rem)', 'x' => '-190px', 'y' => '-180px', 'first' => '#7d2e2b', 'second' => '#e7dedc', 'rotation' => -14, 'opacity' => 0.5, 'cut' => 'curved'],
                ['size' => 350, 'cssSize' => 'clamp(22rem, 42vmin, 48rem)', 'x' => 'calc(100% - 210px)', 'y' => '2svh', 'first' => '#665f55', 'second' => '#f6f1ee', 'rotation' => 36, 'opacity' => 0.42, 'cut' => 'curved'],
                ['size' => 410, 'cssSize' => 'clamp(25rem, 48vmin, 54rem)', 'x' => 'calc(100% - 310px)', 'y' => 'calc(100% - 110px)', 'first' => '#8b5e5a', 'second' => '#fbfaf6', 'rotation' => -58, 'opacity' => 0.52, 'cut' => 'curved'],
                ['size' => 280, 'cssSize' => 'clamp(18rem, 34vmin, 40rem)', 'x' => '-96px', 'y' => 'calc(100% - 150px)', 'first' => '#c9aaa1', 'second' => '#f6f1ee', 'rotation' => 18, 'opacity' => 0.46, 'cut' => 'curved'],
            ],
        ],
    ];

    $layout = $variants[$variant] ?? $variants['classic'];
    $scheme = $layout['scheme'];
@endphp

<main
    class="saint-page"
    data-saint-variant="{{ $variant }}"
    style="--saint-page-start: {{ $scheme['pageStart'] }}; --saint-page-mid: {{ $scheme['pageMid'] }}; --saint-page-end: {{ $scheme['pageEnd'] }}; --saint-card: {{ $scheme['card'] }}; --saint-border: {{ $scheme['border'] }}; --saint-text: {{ $scheme['text'] }}; --saint-accent: {{ $scheme['accent'] }}; --saint-backdrop: {{ $scheme['backdrop'] }};"
>
    <a class="saint-backlink" href="{{ route('search.index') }}">
        <span aria-hidden="true">←</span>
        <span>Back to search</span>
    </a>

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

    <section class="saint-hero" aria-label="{{ $saint->primary_name }}">
        <div class="saint-art">
            @include('saints.image-block', ['slug' => $saint->slug, 'name' => $saint->primary_name, 'gender' => $saint->gender])
        </div>

        @include('saints.copy-panel', ['saint' => $saint, 'subtitle' => $subtitle])
    </section>
</main>
