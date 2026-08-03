<?php

namespace App\Services;

use App\Models\Saint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SaintSearchService
{
    /**
     * @return Collection<int, Saint>|LengthAwarePaginator<int, Saint>|Paginator<int, Saint>
     */
    public function search(
        ?string $query,
        ?string $patronage = null,
        ?string $order = null,
        ?string $type = null,
        ?string $popular = null,
        ?int $perPage = null,
        ?array $with = null,
    ): Collection|LengthAwarePaginator|Paginator
    {
        $normalizedQuery = trim((string) $query);
        $normalizedType = trim((string) $type);
        $normalizedPopular = trim((string) $popular);
        $includeBroadText = mb_strlen($normalizedQuery) >= 3;
        $driver = Saint::query()->getConnection()->getDriverName();
        $virtuesSearchColumn = $this->jsonSearchColumn('virtues', $driver);
        $vicesSearchColumn = $this->jsonSearchColumn('vices', $driver);

        $search = Saint::query()
            ->select($this->resultColumns())
            ->with($with ?? ['aliases', 'feastDays', 'patronages', 'religiousOrders'])
            ->when($normalizedType !== '', fn (Builder $builder) => $builder->where('canonical_status', $normalizedType))
            ->when($normalizedPopular !== '', fn (Builder $builder) => $this->applyPopularFilter($builder, $normalizedPopular))
            ->when($normalizedQuery !== '', function (Builder $builder) use ($normalizedQuery, $virtuesSearchColumn, $vicesSearchColumn, $includeBroadText): void {
                $like = '%'.strtolower($normalizedQuery).'%';

                $builder->where(function (Builder $query) use ($like, $virtuesSearchColumn, $vicesSearchColumn, $includeBroadText): void {
                    $query
                        ->whereRaw('lower(primary_name) like ?', [$like])
                        ->when($includeBroadText, function (Builder $query) use ($like, $virtuesSearchColumn, $vicesSearchColumn): void {
                            $query
                                ->orWhereRaw("lower(coalesce({$virtuesSearchColumn}, '')) like ?", [$like])
                                ->orWhereRaw("lower(coalesce({$vicesSearchColumn}, '')) like ?", [$like]);
                        })
                        ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->whereRaw('lower(alias) like ?', [$like]))
                        ->orWhereHas('patronages', function (Builder $patronages) use ($like, $includeBroadText): void {
                            $patronages->where(function (Builder $query) use ($like, $includeBroadText): void {
                                $query
                                    ->whereRaw('lower(name) like ?', [$like])
                                    ->orWhereRaw('lower(slug) like ?', [$like])
                                    ->when($includeBroadText, fn (Builder $query) => $query->orWhereRaw("lower(coalesce(description, '')) like ?", [$like]));
                            });
                        });
                });
            })
            ->when($patronage, fn (Builder $builder) => $builder->whereHas(
                'patronages',
                fn (Builder $patronages) => $patronages->where('slug', $patronage)
            ))
            ->when($order, fn (Builder $builder) => $builder->whereHas(
                'religiousOrders',
                fn (Builder $orders) => $orders->where('slug', $order)
            ))
            ->orderBy('primary_name');

        if ($perPage !== null) {
            return $search->simplePaginate($perPage);
        }

        return $search->limit(50)->get();
    }

    /**
     * Keep search queries off `select *` so pooled Postgres connections do not
     * reuse stale result plans after saint table migrations add columns.
     *
     * @return list<string>
     */
    private function resultColumns(): array
    {
        return [
            'id',
            'primary_name',
            'slug',
            'biography',
            'biography_sections',
            'biography_sources',
            'biography_format_model',
            'biography_formatted_at',
            'biography_format_error',
            'profile_summary',
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
            'created_at',
            'updated_at',
        ];
    }

    private function applyPopularFilter(Builder $builder, string $filter): void
    {
        match ($filter) {
            'patrons' => $builder->whereHas('patronages'),
            'martyrs' => $this->whereBoolean($builder, 'is_martyr'),
            'men' => $builder->where('gender', 'male'),
            'women' => $builder->where('gender', 'female'),
            'doctors' => $this->whereBoolean($builder, 'is_doctor'),
            default => null,
        };
    }

    private function whereBoolean(Builder $builder, string $column): void
    {
        if ($builder->getConnection()->getDriverName() === 'pgsql') {
            $builder->whereRaw($builder->getQuery()->getGrammar()->wrap($column).' is true');

            return;
        }

        $builder->where($column, true);
    }

    private function jsonSearchColumn(string $column, string $driver): string
    {
        return match ($driver) {
            'pgsql' => "\"{$column}\"::text",
            default => $column,
        };
    }
}
