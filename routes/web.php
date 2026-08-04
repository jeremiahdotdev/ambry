<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\DeveloperApiKeyController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\SaintProfileController;
use App\Http\Controllers\SearchController;
use App\Support\GeneratedSaintImages;
use Illuminate\Support\Facades\Route;

Route::get('/', [SearchController::class, 'index'])->name('search.index');
Route::get('/search', [SearchController::class, 'search'])->name('search.results');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/signup', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/signup', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

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

Route::middleware('auth')->group(function (): void {
    Route::redirect('/developers', '/developers/api-keys')
        ->name('developers.index');
    Route::get('/developers/api-keys', [DeveloperApiKeyController::class, 'index'])
        ->name('developers.api-keys.index');
    Route::post('/developers/api-keys', [DeveloperApiKeyController::class, 'store'])
        ->name('developers.api-keys.store');
    Route::delete('/developers/api-keys/{apiKey}', [DeveloperApiKeyController::class, 'destroy'])
        ->name('developers.api-keys.destroy');
});
