<?php

namespace App\Http\Controllers;

use App\Models\Saint;
use Illuminate\Contracts\View\View;

class SaintProfileController extends Controller
{
    public function profile(Saint $saint): View
    {
        return view('saints.index', [
            'saint' => $saint,
            'subtitle' => match ($saint->slug) {
                'st-patrick' => 'Bishop and Patron of Ireland',
                default => null,
            },
            'variant' => match ($saint->slug) {
                'st-patrick' => 'classic',
                default => 'default',
            },
        ]);
    }
}
