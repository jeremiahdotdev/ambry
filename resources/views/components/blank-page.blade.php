@props([
    'title' => config('app.name', 'Ambry'),
    'assets' => [],
    'bodyClass' => 'search-body',
    'pageClass' => '',
    'showNav' => true,
    'showFooter' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(array_values(array_unique(array_merge([
        'resources/css/app.css',
        'resources/css/search/index.css',
        'resources/css/search/nav.css',
        'resources/css/components/circles/search.css',
        'resources/css/components/form-input.css',
        'resources/css/components/form-button.css',
    ], $assets))))
</head>
<body class="{{ $bodyClass }}">
    <main class="search-page {{ $pageClass }}">
        @if ($showNav)
            <x-navbar />
        @endif

        {{ $slot }}

        @if ($showFooter)
            <footer class="search-footer search-chrome">
                <span>&copy; {{ now()->year }}</span>
                <span>Maintained by <a href="https://jeremiah.dev">jeremiah.dev</a></span>
            </footer>
        @endif

        @include('components.circles.search', ['variant' => 'coral'])
        @include('components.circles.search', ['variant' => 'ivory'])
        @include('components.circles.search', ['variant' => 'sage'])
        @include('components.circles.search', ['variant' => 'gold'])
        @include('components.circles.search', ['variant' => 'blue'])
        {{ $circles ?? '' }}
    </main>
</body>
</html>
