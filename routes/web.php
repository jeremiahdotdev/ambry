<?php

use App\Http\Controllers\SearchController;
use App\Http\Controllers\SaintProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SearchController::class, 'index'])->name('search.index');
Route::get('/search', [SearchController::class, 'search'])->name('search.results');
Route::get('/saints/{saint:slug}', [SaintProfileController::class, 'profile'])->name('saints.profile');
