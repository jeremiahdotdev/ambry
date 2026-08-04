<?php

namespace App\Support;

class GeneratedSaintImages
{
    private const FILES = [
        'portrait' => 'portrait.webp',
        'thumb' => 'thumb.webp',
        'original' => 'original.png',
    ];

    public static function path(string $slug, string $kind): ?string
    {
        $file = self::FILES[$kind] ?? null;

        if (! $file) {
            return null;
        }

        $path = storage_path("app/generated/saints/{$slug}/{$file}");

        return is_file($path) ? $path : null;
    }

    public static function url(string $slug, string $kind): ?string
    {
        if (! self::path($slug, $kind)) {
            return null;
        }

        return route('generated.saint-image', ['slug' => $slug, 'kind' => $kind]);
    }

    public static function metadata(string $slug): array
    {
        $path = storage_path("app/generated/saints/{$slug}/metadata.json");

        if (! is_file($path)) {
            return [];
        }

        $metadata = json_decode((string) file_get_contents($path), true);

        return is_array($metadata) ? $metadata : [];
    }

    public static function recommendedVariant(string $slug): ?string
    {
        $variant = self::metadata($slug)['design_recommendation']['recommended_page_variant'] ?? null;

        if (! is_string($variant)) {
            return null;
        }

        return in_array($variant, SaintPageVariants::names(), true) ? $variant : null;
    }
}
