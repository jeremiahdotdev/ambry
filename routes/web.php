<?php

use App\Http\Controllers\SearchController;
use App\Http\Controllers\SaintProfileController;
use App\Support\GeneratedSaintImages;
use Illuminate\Support\Facades\Route;

Route::get('/', [SearchController::class, 'index'])->name('search.index');
Route::get('/search', [SearchController::class, 'search'])->name('search.results');
Route::get('/generated/saints/{slug}/{kind}', function (string $slug, string $kind) {
    $path = GeneratedSaintImages::path($slug, $kind);

    abort_unless($path, 404);

    return response()->file($path, [
        'Cache-Control' => app()->isLocal()
            ? 'no-store'
            : 'public, max-age=31536000, immutable',
    ]);
})
    ->where('slug', '[A-Za-z0-9-]+')
    ->where('kind', 'portrait|thumb|original')
    ->name('generated.saint-image');
Route::get('/saints/{saint:slug}', [SaintProfileController::class, 'profile'])->name('saints.profile');
