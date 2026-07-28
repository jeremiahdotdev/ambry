<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $saint->primary_name }} | {{ config('app.name', 'Ambry') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite([
        'resources/css/app.css',
        'resources/css/saints/index.css',
        'resources/css/saints/profile.css',
        'resources/css/shared/circles/bisected.css',
        'resources/css/saints/image-block.css',
        'resources/css/saints/copy-panel.css',
        'resources/css/saints/title-block.css',
        'resources/css/saints/life-dates.css',
    ])
</head>
<body class="saint-profile-body">
    @include('saints.profile', ['saint' => $saint, 'subtitle' => $subtitle, 'variant' => $variant])
</body>
</html>
