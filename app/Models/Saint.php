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
    ];

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'death_year' => 'integer',
            'is_martyr' => 'boolean',
            'is_doctor' => 'boolean',
            'virtues' => 'array',
            'vices' => 'array',
            'roles' => 'array',
            'ai_confidence' => 'decimal:3',
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

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'source_saint_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'target_saint_id');
    }
}
