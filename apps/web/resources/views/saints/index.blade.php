<!DOCTYPE html>
@php($canEditSaints ??= false)
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $saint->displayName() }} | {{ config('app.name', 'Ambry') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/saints/index.css',
        'resources/css/saints/profile.css',
        'resources/css/components/circles/bisected.css',
        'resources/css/saints/image-block.css',
        'resources/css/saints/copy-panel.css',
        'resources/css/saints/title-block.css',
        'resources/css/saints/life-dates.css',
    ])
    @livewireStyles
</head>
<body class="saint-profile-body">
    @include('saints.profile', ['saint' => $saint, 'canEditSaints' => $canEditSaints, 'subtitle' => $subtitle, 'variant' => $variant])
    @livewireScriptConfig
</body>
</html>
