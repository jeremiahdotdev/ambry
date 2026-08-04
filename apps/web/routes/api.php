<?php

use App\Http\Controllers\Api\SaintController;
use Illuminate\Support\Facades\Route;

Route::middleware('developer.api-key')->group(function (): void {
    Route::get('/saints', [SaintController::class, 'index']);
    Route::get('/saints/{saint:slug}', [SaintController::class, 'show']);
    Route::get('/search', [SaintController::class, 'index']);
});
