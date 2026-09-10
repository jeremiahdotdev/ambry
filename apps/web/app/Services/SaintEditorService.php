<?php

namespace App\Services;

use App\Models\Saint;
use App\Support\SaintPageVariants;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SaintEditorService
{
    public const STATUS_OPTIONS = [
        'saint' => 'Saint',
        'pope' => 'Pope',
        'blessed' => 'Blessed',
        'venerable' => 'Venerable',
        'holy_person' => 'Holy Person',
    ];

    public const YEAR_QUALIFIER_OPTIONS = [
        '' => 'Unspecified',
        'exact' => 'Exact',
        'circa' => 'Circa',
        'before' => 'Before',
        'after' => 'After',
    ];

    public const IMAGE_VARIANT_OPTIONS = [
        '' => 'Auto',
    ];

    public function rules(Saint $saint): array
    {
        return [
            'primary_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('saints', 'slug')->ignore($saint->id),
            ],
            'canonical_status' => ['required', Rule::in(array_keys(SaintEditorService::STATUS_OPTIONS))],
            'gender' => ['nullable', 'string', 'max:80'],
            'birth_year' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'birth_year_qualifier' => ['nullable', Rule::in(array_keys(SaintEditorService::YEAR_QUALIFIER_OPTIONS))],
            'death_year' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'death_year_qualifier' => ['nullable', Rule::in(array_keys(SaintEditorService::YEAR_QUALIFIER_OPTIONS))],
            'life_dates' => ['nullable', 'string', 'max:255'],
            'is_martyr' => ['nullable', 'boolean'],
            'is_doctor' => ['nullable', 'boolean'],
            'profile_subtitle' => ['nullable', 'string', 'max:255'],
            'profile_summary' => ['nullable', 'string', 'max:6000'],
            'biography' => ['nullable', 'string', 'max:20000'],
            'image_page_variant' => ['nullable', Rule::in(array_keys($this->imageVariantOptions()))],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Saint $saint, array $attributes): Saint
    {
        $saint->fill([
            'primary_name' => $attributes['primary_name'],
            'slug' => $attributes['slug'],
            'canonical_status' => $attributes['canonical_status'],
            'gender' => $this->nullableString($attributes, 'gender'),
            'birth_year' => $this->nullableInteger($attributes, 'birth_year'),
            'birth_year_qualifier' => $this->nullableString($attributes, 'birth_year_qualifier'),
            'death_year' => $this->nullableInteger($attributes, 'death_year'),
            'death_year_qualifier' => $this->nullableString($attributes, 'death_year_qualifier'),
            'life_dates' => $this->nullableString($attributes, 'life_dates'),
            'is_martyr' => (bool) ($attributes['is_martyr'] ?? false),
            'is_doctor' => (bool) ($attributes['is_doctor'] ?? false),
            'profile_subtitle' => $this->nullableString($attributes, 'profile_subtitle'),
            'profile_summary' => $this->nullableString($attributes, 'profile_summary'),
            'biography' => $this->nullableString($attributes, 'biography'),
            'image_page_variant' => $this->nullableString($attributes, 'image_page_variant'),
        ]);

        $saint->save();

        return $saint;
    }

    /**
     * @return array<string, string>
     */
    public function imageVariantOptions(): array
    {
        return self::IMAGE_VARIANT_OPTIONS + collect(SaintPageVariants::names())
            ->mapWithKeys(fn (string $variant): array => [$variant => str($variant)->replace('-', ' ')->title()->toString()])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function nullableString(array $attributes, string $key): ?string
    {
        $value = trim((string) Arr::get($attributes, $key, ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function nullableInteger(array $attributes, string $key): ?int
    {
        $value = Arr::get($attributes, $key);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
