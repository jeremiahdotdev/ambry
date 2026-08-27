<?php

namespace App\Http\Controllers;

use App\Models\Saint;
use App\Services\SaintEditorPermissionService;
use App\Support\GeneratedSaintImages;
use App\Support\SaintPageVariants;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SaintProfileController extends Controller
{
    public function __construct(
        private readonly SaintEditorPermissionService $permissions,
    ) {}

    public function profile(Request $request, Saint $saint): View
    {
        $saint->load([
            'patronages' => fn ($query) => $query->orderBy('name'),
        ]);

        return view('saints.index', [
            'saint' => $saint,
            'canEditSaints' => $this->permissions->canEditSaints($request->user()),
            'subtitle' => $saint->profile_subtitle,
            'variant' => match ($saint->slug) {
                'st-patrick' => 'classic-gold',
                default => $saint->image_page_variant
                    ?? GeneratedSaintImages::recommendedVariant($saint->slug)
                    ?? SaintPageVariants::defaultForSlug($saint->slug),
            },
        ]);
    }
}
