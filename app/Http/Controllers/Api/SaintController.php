<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Saint;
use App\Services\SaintSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaintController extends Controller
{
    public function index(
        Request $request,
        SaintSearchService $search,
    ): JsonResponse
    {
        $saints = $search->search(
            $request->query('q'),
            $request->query('patronage'),
            $request->query('order'),
        );

        return response()->json([
            'data' => $saints->map(fn (Saint $saint): array => $this->serializeSaint($saint))->values(),
        ]);
    }

    public function show(Saint $saint): JsonResponse
    {
        $saint->load(['aliases', 'feastDays', 'patronages', 'religiousOrders']);

        return response()->json([
            'data' => $this->serializeSaint($saint),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSaint(Saint $saint): array
    {
        return [
            'id' => $saint->id,
            'name' => $saint->displayName(),
            'slug' => $saint->slug,
            'biography' => $saint->displayBiography(),
            'birth_year' => $saint->birth_year,
            'birth_year_qualifier' => $saint->birth_year_qualifier,
            'death_year' => $saint->death_year,
            'death_year_qualifier' => $saint->death_year_qualifier,
            'life_dates' => $saint->life_dates,
            'gender' => $saint->gender,
            'canonical_status' => $saint->canonical_status,
            'is_martyr' => $saint->is_martyr,
            'is_doctor' => $saint->is_doctor,
            'image_cutout_url' => $saint->image_cutout_url,
            'image_portrait_url' => $saint->image_portrait_url,
            'image_thumb_url' => $saint->image_thumb_url,
            'aliases' => $saint->aliases->pluck('alias')->values(),
            'feast_days' => $saint->feastDays->map(fn ($feastDay): array => [
                'month' => $feastDay->month,
                'day' => $feastDay->day,
                'calendar' => $feastDay->calendar,
            ])->values(),
            'patronages' => $saint->patronages->pluck('name')->values(),
            'religious_orders' => $saint->religiousOrders->pluck('name')->values(),
        ];
    }
}
