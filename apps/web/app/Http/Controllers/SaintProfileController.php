<?php

namespace App\Http\Controllers;

use App\Models\Saint;
use App\Support\GeneratedSaintImages;
use App\Support\SaintPageVariants;
use Illuminate\Contracts\View\View;

class SaintProfileController extends Controller
{
    public function profile(Saint $saint): View
    {
        $saint->load([
            'patronages' => fn ($query) => $query->orderBy('name'),
        ]);

        return view('saints.index', [
            'saint' => $saint,
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
