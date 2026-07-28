@php
    use App\Support\GeneratedSaintImages;

    $gender ??= null;
    $relativePath = "saints/{$slug}.png";
    $generatedImageUrl = $saint->image_portrait_url ?? GeneratedSaintImages::url($slug, 'portrait');
    $hasImage = file_exists(public_path($relativePath));
    $fallbackPath = $gender === 'female' ? 'saints/default_female.png' : 'saints/default.png';
    $imageUrl = $generatedImageUrl ?? asset($hasImage ? $relativePath : $fallbackPath);
@endphp

<figure class="saint-image-block">
    <img src="{{ $imageUrl }}" alt="{{ $name }}">
</figure>
