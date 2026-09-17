<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('storefront');
});

Route::get('/storefront', [StorefrontController::class, 'index'])->name('storefront');
Route::get('/storefront/search-suggestions', [StorefrontController::class, 'searchSuggestions'])
    ->name('storefront.search-suggestions');
