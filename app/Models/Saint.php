<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Saint extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'primary_name',
        'slug',
        'biography',
        'biography_sections',
        'biography_sources',
        'biography_format_model',
        'biography_formatted_at',
        'biography_format_error',
        'profile_summary',
        'profile_subtitle',
        'profile_life_span',
        'profile_patronages',
        'profile_temperaments',
        'profile_key_struggles',
        'profile_key_virtues',
        'profile_church_roles',
        'profile_feast_days',
        'profile_related_saints',
        'profile_works',
        'profile_landmarks',
        'profile_sources',
        'profile_source_block',
        'profile_research_notes',
        'birth_year',
        'birth_year_qualifier',
        'death_year',
        'death_year_qualifier',
        'life_dates',
        'gender',
        'canonical_status',
        'is_martyr',
        'is_doctor',
        'virtues',
        'vices',
        'roles',
        'ai_reason',
        'ai_confidence',
        'image_prompt',
        'image_cutout_url',
        'image_portrait_url',
        'image_thumb_url',
        'image_page_variant',
        'image_key_colors',
        'image_variant_reason',
        'image_variant_confidence',
    ];

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'death_year' => 'integer',
            'is_martyr' => 'boolean',
            'is_doctor' => 'boolean',
            'biography_sections' => 'array',
            'biography_sources' => 'array',
            'biography_formatted_at' => 'datetime',
            'profile_life_span' => 'array',
            'profile_patronages' => 'array',
            'profile_temperaments' => 'array',
            'profile_key_struggles' => 'array',
            'profile_key_virtues' => 'array',
            'profile_church_roles' => 'array',
            'profile_feast_days' => 'array',
            'profile_related_saints' => 'array',
            'profile_works' => 'array',
            'profile_landmarks' => 'array',
            'profile_sources' => 'array',
            'profile_source_block' => 'array',
            'profile_research_notes' => 'array',
            'virtues' => 'array',
            'vices' => 'array',
            'roles' => 'array',
            'ai_confidence' => 'decimal:3',
            'image_key_colors' => 'array',
            'image_variant_confidence' => 'decimal:3',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function displayLifeDates(): ?string
    {
        $birth = $this->formatLifeYear($this->birth_year, $this->birth_year_qualifier);
        $death = $this->formatLifeYear($this->death_year, $this->death_year_qualifier);

        if ($birth && $death) {
            return "{$birth}-{$death} AD";
        }

        if ($birth) {
            return "b. {$birth} AD";
        }

        if ($death) {
            return "d. {$death} AD";
        }

        return $this->formatLifeDatesString($this->life_dates);
    }

    public function displayProfileLifeDates(): ?string
    {
        $lifeSpan = is_array($this->profile_life_span) ? $this->profile_life_span : [];
        $birthDate = $lifeSpan['birth'] ?? null;
        $deathDate = $lifeSpan['death'] ?? null;
        $birth = $this->formatProfileLifeDate($birthDate);
        $death = $this->formatProfileLifeDate($deathDate);

        if ($birth && $death) {
            return "{$birth}-{$death} AD";
        }

        if ($birth) {
            return "b. {$birth} AD";
        }

        if ($death) {
            return "d. {$death} AD";
        }

        $activeCenturies = collect($lifeSpan['active_centuries'] ?? [])
            ->filter(fn ($century): bool => is_numeric($century))
            ->map(fn ($century): int => (int) $century)
            ->unique()
            ->sort()
            ->values();

        if ($activeCenturies->isEmpty()) {
            return null;
        }

        if ($activeCenturies->count() === 1) {
            return 'Active '.$this->formatCentury($activeCenturies->first());
        }

        return 'Active '.$this->formatCenturyOrdinal($activeCenturies->first()).'-'.$this->formatCenturyOrdinal($activeCenturies->last()).' centuries';
    }

    public function displayName(): string
    {
        return self::stripLeadingHonorific((string) $this->primary_name);
    }

    public function displayCanonicalStatus(): string
    {
        return match ($this->canonical_status) {
            'saint' => 'Saint',
            'pope' => 'Pope',
            'blessed' => 'Blessed',
            'venerable' => 'Venerable',
            'holy_person' => 'Holy Person',
            default => Str::of((string) $this->canonical_status)
                ->replace('_', ' ')
                ->title()
                ->toString() ?: 'Holy Person',
        };
    }

    public function displayBiography(): ?string
    {
        $biography = trim(strip_tags((string) $this->biography));

        if ($biography === '') {
            return null;
        }

        $biography = $this->stripLeadingRepeatedName($biography);

        return $biography === '' ? null : $biography;
    }

    /**
     * @return list<string>
     */
    public function displayBiographyParagraphs(): array
    {
        $biography = $this->displayBiography();

        if (! $biography) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\R{2,}/', $biography) ?: []),
            fn (string $paragraph): bool => $paragraph !== '',
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function displayBiographySections(): array
    {
        return collect($this->biography_sections ?? [])
            ->filter(fn ($section): bool => is_array($section) && (
                filled($section['body'] ?? null)
                || (($section['kind'] ?? null) === 'sources' && filled($section['pageSource']['url'] ?? null))
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function displayBiographySources(): array
    {
        return collect($this->biography_sources ?? [])
            ->filter(fn ($source): bool => is_array($source) && filled($source['url'] ?? null))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function displayProfileSummaryParagraphs(): array
    {
        $summary = trim((string) $this->profile_summary);

        if ($summary === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\R{2,}/u', $summary) ?: []),
            fn (string $paragraph): bool => $paragraph !== '',
        ));
    }

    /**
     * @return list<string>
     */
    public function displayProfileRoles(): array
    {
        $roles = collect($this->profile_church_roles ?? [])
            ->filter(fn ($role): bool => is_array($role) && filled($role['label'] ?? $role['role'] ?? null))
            ->map(function (array $role): string {
                $label = (string) ($role['label'] ?? $this->humanizeProfileLabel((string) ($role['role'] ?? '')));

                return trim($label);
            })
            ->filter(fn (string $label): bool => $label !== '')
            ->values();

        if ($this->is_martyr && ! $roles->contains(fn (string $role): bool => strtolower($role) === 'martyr')) {
            $roles->push('Martyr');
        }

        return $roles->all();
    }

    /**
     * @return list<array{label: string, score: int}>
     */
    public function displayProfileTemperamentScores(): array
    {
        $temperaments = is_array($this->profile_temperaments ?? null) ? $this->profile_temperaments : [];
        $scores = collect($temperaments['scores'] ?? [])
            ->filter(fn ($score, $label): bool => filled($label) && is_numeric($score))
            ->map(fn ($score): float => max(0, (float) $score));
        $maxScore = (float) $scores->max();
        $scale = $maxScore > 0 ? 75 / $maxScore : 0;

        return $scores->map(function (float $score, string|int $label) use ($scale): array {
            return [
                'label' => $this->humanizeProfileLabel((string) $label),
                'score' => max(0, min(75, (int) round($score * $scale))),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{name: string, summary: ?string}>
     */
    public function displayProfileVirtues(): array
    {
        return $this->displayProfileNamedSummaries($this->profile_key_virtues);
    }

    /**
     * @return list<array{name: string, summary: ?string}>
     */
    public function displayProfileVices(): array
    {
        return $this->displayProfileNamedSummaries($this->profile_key_struggles);
    }

    /**
     * @return list<array{name: string, date: ?string}>
     */
    public function displayProfileFeastDays(): array
    {
        return collect($this->profile_feast_days ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['name'] ?? null))
            ->map(function (array $item): array {
                $recurrence = is_array($item['recurrence'] ?? null) ? $item['recurrence'] : [];

                return [
                    'name' => trim((string) $item['name']),
                    'date' => $this->formatProfileFeastDate($recurrence),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, slug: ?string}>
     */
    public function displayProfileRelatedSaints(): array
    {
        return collect($this->profile_related_saints ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['name'] ?? null))
            ->map(fn (array $item): array => [
                'name' => trim((string) $item['name']),
                'slug' => filled($item['slug'] ?? null) ? trim((string) $item['slug']) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, description: ?string}>
     */
    public function displayProfileWorks(): array
    {
        return collect($this->profile_works ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['name'] ?? null))
            ->map(fn (array $item): array => [
                'name' => trim((string) $item['name']),
                'description' => filled($item['description'] ?? null) ? trim((string) $item['description']) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, location: ?string, description: ?string}>
     */
    public function displayProfileLandmarks(): array
    {
        return collect($this->profile_landmarks ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['name'] ?? null))
            ->map(fn (array $item): array => [
                'name' => trim((string) $item['name']),
                'location' => filled($item['location'] ?? null) ? trim((string) $item['location']) : null,
                'description' => filled($item['description'] ?? null) ? trim((string) $item['description']) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function displayProfileResearchNotes(): array
    {
        return collect($this->profile_research_notes ?? [])
            ->filter(fn ($item): bool => filled($item))
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $note): bool => $note !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{url: ?string, citation: string}>
     */
    public function displayProfileSources(): array
    {
        return collect($this->profile_sources ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? null))
            ->map(fn (array $item): array => [
                'url' => filled($item['url'] ?? null) ? trim((string) $item['url']) : null,
                'citation' => $this->formatProfileCitation($item),
            ])
            ->values()
            ->all();
    }

    public function hasProfileEnrichment(): bool
    {
        return $this->displayProfileTemperamentScores() !== []
            || $this->displayProfileVirtues() !== []
            || $this->displayProfileVices() !== []
            || $this->displayProfileFeastDays() !== []
            || $this->displayProfileRelatedSaints() !== []
            || $this->displayProfileWorks() !== []
            || $this->displayProfileLandmarks() !== []
            || $this->displayProfileSources() !== []
            || $this->displayProfileResearchNotes() !== [];
    }

    private function formatLifeYear(?int $year, ?string $qualifier): ?string
    {
        if (! $year) {
            return null;
        }

        return in_array($qualifier, ['circa', 'probable'], true)
            ? "c. {$year}"
            : (string) $year;
    }

    private function formatLifeDatesString(?string $lifeDates): ?string
    {
        $lifeDates = trim((string) $lifeDates);

        if ($lifeDates === '') {
            return null;
        }

        $lifeDates = preg_replace('/\b(\d{1,4})\s*AD\s*[-–—]\s*(\d{1,4})\s*AD\b/i', '$1-$2 AD', $lifeDates);

        return preg_replace('/\s+[-–—]\s+/', '-', $lifeDates);
    }

    private function formatProfileLifeDate(mixed $date): ?string
    {
        if (! is_array($date)) {
            return null;
        }

        $year = $this->yearFromProfileDate($date);

        if (! $year) {
            return null;
        }

        return ! empty($date['is_circa']) || ($date['certainty'] ?? null) === 'approximate'
            ? "c. {$year}"
            : (string) $year;
    }

    private function yearFromProfileDate(array $date): ?int
    {
        if (is_numeric($date['year'] ?? null)) {
            return (int) $date['year'];
        }

        $timestamp = (string) ($date['timestamp'] ?? '');

        if (preg_match('/^(\d{1,4})-/', $timestamp, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function formatCentury(int $century): string
    {
        return $this->formatCenturyOrdinal($century).' century';
    }

    private function formatCenturyOrdinal(int $century): string
    {
        $absolute = abs($century);
        $suffix = 'th';

        if (! in_array($absolute % 100, [11, 12, 13], true)) {
            $suffix = match ($absolute % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };
        }

        return "{$century}{$suffix}";
    }

    private function stripLeadingRepeatedName(string $biography): string
    {
        $paragraphs = preg_split('/\R{2,}/', $biography) ?: [$biography];
        $first = trim($paragraphs[0] ?? '');

        if ($first !== '' && $this->sameDisplayName($first, (string) $this->primary_name)) {
            array_shift($paragraphs);

            return trim(implode("\n\n", $paragraphs));
        }

        foreach ($this->nameVariants() as $name) {
            $pattern = '/^\s*'.preg_quote($name, '/').'\s*(?:\R+|[-–—:]\s+)/iu';
            $stripped = preg_replace($pattern, '', $biography, 1);

            if (is_string($stripped) && $stripped !== $biography) {
                return trim($stripped);
            }
        }

        return $biography;
    }

    /**
     * @return list<string>
     */
    private function nameVariants(): array
    {
        return array_values(array_unique(array_filter([
            (string) $this->primary_name,
            $this->displayName(),
        ])));
    }

    private function sameDisplayName(string $left, string $right): bool
    {
        return $this->normalizeNameForComparison($left) === $this->normalizeNameForComparison($right);
    }

    private function normalizeNameForComparison(string $name): string
    {
        $name = self::stripLeadingHonorific($name);
        $name = preg_replace('/[^\pL\pN]+/u', ' ', $name) ?? $name;

        return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    /**
     * @return list<array{name: string, summary: ?string}>
     */
    private function displayProfileNamedSummaries(mixed $items): array
    {
        return collect($items ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['name'] ?? null))
            ->map(fn (array $item): array => [
                'name' => $this->humanizeProfileLabel(trim((string) $item['name'])),
                'summary' => filled($item['summary'] ?? null) ? trim((string) $item['summary']) : null,
            ])
            ->values()
            ->all();
    }

    private function humanizeProfileLabel(string $value): string
    {
        return Str::of($value)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function formatProfileFeastDate(array $recurrence): ?string
    {
        if (! filled($recurrence['month'] ?? null) || ! filled($recurrence['day'] ?? null)) {
            return null;
        }

        $monthNames = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];

        return ($monthNames[(int) $recurrence['month']] ?? (string) $recurrence['month']).' '.(int) $recurrence['day'];
    }

    private function formatProfileCitation(array $source): string
    {
        $isNewAdvent = ($source['source_type'] ?? null) === 'new_advent'
            || str_contains((string) ($source['url'] ?? ''), 'newadvent.org');
        $publisherOrAuthor = trim((string) ($source['publisher_or_author'] ?? ''));
        $title = trim((string) ($source['title'] ?? ''));
        $accessed = filled($source['accessed_date'] ?? null)
            ? 'Accessed '.$source['accessed_date']
            : 'Accessed '.now()->year;
        $parts = collect([
            filled($publisherOrAuthor) ? rtrim($publisherOrAuthor, '.') : null,
            filled($title) ? '"'.rtrim($title, '.').'."' : null,
            $isNewAdvent ? 'New Advent' : null,
            $accessed,
        ])->filter()->implode('. ');

        return preg_replace('/\."\.\s+/', '." ', Str::finish($parts, '.')) ?? Str::finish($parts, '.');
    }

    private static function stripLeadingHonorific(string $name): string
    {
        return trim(preg_replace('/^(?:(?:Pope|St\.?|Saint|Bl\.?|Blessed|Ven\.?|Venerable)\s+)+/iu', '', $name) ?? $name);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(SaintAlias::class);
    }

    public function feastDays(): HasMany
    {
        return $this->hasMany(FeastDay::class);
    }

    public function patronages(): BelongsToMany
    {
        return $this->belongsToMany(Patronage::class)
            ->withPivot(['citation_id', 'confidence', 'is_tradition'])
            ->withTimestamps();
    }

    public function religiousOrders(): BelongsToMany
    {
        return $this->belongsToMany(ReligiousOrder::class)
            ->withPivot(['role', 'citation_id', 'confidence'])
            ->withTimestamps();
    }

}
