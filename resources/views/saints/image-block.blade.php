@php
    $gender ??= null;
    $relativePath = "saints/{$slug}.png";
    $hasImage = file_exists(public_path($relativePath));
    $fallbackPath = $gender === 'female' ? 'saints/default_female.png' : 'saints/default.png';
    $imagePath = $hasImage ? $relativePath : $fallbackPath;
@endphp

<figure class="saint-image-block">
    <img src="{{ asset($imagePath) }}" alt="{{ $name }}">
</figure>
