<?php

namespace App\Support;

class SearchFilters
{
    public const SEARCH_TYPES = [
        'saint' => 'Saint',
        'pope' => 'Pope',
        'blessed' => 'Blessed',
        'venerable' => 'Venerable',
    ];

    public const POPULAR_SEARCHES = [
        'patrons' => [
            'label' => 'Patron Saints',
            'icon' => 'shield-check',
        ],
        'martyrs' => [
            'label' => 'Martyrs',
            'icon' => 'flame',
        ],
        'men' => [
            'label' => 'Men',
            'icon' => 'mars',
        ],
        'women' => [
            'label' => 'Women',
            'icon' => 'venus',
        ],
        'doctors' => [
            'label' => 'Doctors',
            'icon' => 'graduation-cap',
        ],
    ];

    public function selectedType(string $rawType): string
    {
        $selectedType = array_key_exists($rawType, self::SEARCH_TYPES) ? $rawType : 'saint';

        return $selectedType;
    }

    public function selectedPopularSearch(string $popularSearch): ?string
    {
        return array_key_exists($popularSearch, self::POPULAR_SEARCHES) ? $popularSearch : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function normalizedQueryAndType(string $query, string $type): array
    {
        $query = trim($query);
        $selectedType = $this->selectedType($type);
        $prefixType = null;

        while (preg_match('/^(st|saint|pope|bl|blessed|ven|venerable)\.?\s+/iu', $query, $matches) === 1) {
            $prefixType ??= $this->typeForSearchPrefix($matches[1]);
            $query = trim((string) preg_replace('/^'.preg_quote($matches[0], '/').'/u', '', $query));
        }

        return [$query, $prefixType ?? $selectedType];
    }

    private function typeForSearchPrefix(string $prefix): string
    {
        return match (strtolower(rtrim($prefix, '.'))) {
            'pope' => 'pope',
            'bl', 'blessed' => 'blessed',
            'ven', 'venerable' => 'venerable',
            default => 'saint',
        };
    }
}
