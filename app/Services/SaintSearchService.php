<?php

namespace App\Services;

use App\Models\Saint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SaintSearchService
{
    /**
     * @return Collection<int, Saint>
     */
    public function search(
        ?string $query,
        ?string $patronage = null,
        ?string $order = null,
        ?string $type = null,
    ): Collection
    {
        $normalizedQuery = trim((string) $query);
        $normalizedType = trim((string) $type);

        return Saint::query()
            ->with(['aliases', 'feastDays', 'patronages', 'religiousOrders'])
            ->when($normalizedType !== '', fn (Builder $builder) => $builder->where('canonical_status', $normalizedType))
            ->when($normalizedQuery !== '', function (Builder $builder) use ($normalizedQuery): void {
                $like = '%'.strtolower($normalizedQuery).'%';

                $builder->where(function (Builder $query) use ($like): void {
                    $query
                        ->whereRaw('lower(primary_name) like ?', [$like])
                        ->orWhereRaw("lower(coalesce(virtues, '')) like ?", [$like])
                        ->orWhereRaw("lower(coalesce(vices, '')) like ?", [$like])
                        ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->whereRaw('lower(alias) like ?', [$like]))
                        ->orWhereHas('patronages', function (Builder $patronages) use ($like): void {
                            $patronages->where(function (Builder $query) use ($like): void {
                                $query
                                    ->whereRaw('lower(name) like ?', [$like])
                                    ->orWhereRaw('lower(slug) like ?', [$like])
                                    ->orWhereRaw("lower(coalesce(description, '')) like ?", [$like]);
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
            ->orderBy('primary_name')
            ->limit(50)
            ->get();
    }
}
