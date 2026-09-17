<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| W68 Product Catalog
|--------------------------------------------------------------------------
| Root URL directly loads the storefront:
| http://localhost/w68_Pricelist/public/
|
| IMPORTANT:
| /storefront is intentionally kept as the named "storefront" route because
| the existing Blade view, filters, search, pagination and JavaScript were
| already built around that working route.
*/
Route::get('/', [StorefrontController::class, 'index']);

Route::get('/storefront', [StorefrontController::class, 'index'])
    ->name('storefront');

Route::get('/storefront/search-suggestions', [StorefrontController::class, 'searchSuggestions'])
    ->name('storefront.search-suggestions');

/*
|--------------------------------------------------------------------------
| Simple Login
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
