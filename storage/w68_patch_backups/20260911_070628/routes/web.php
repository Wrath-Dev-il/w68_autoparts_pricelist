<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| W68 Storefront
|--------------------------------------------------------------------------
| The storefront itself is now the root page:
| http://localhost/w68_Pricelist/public/
*/
Route::get('/', [StorefrontController::class, 'index'])->name('storefront');

/*
| Keep the old /storefront URL working, but send it to the new root URL.
*/
Route::get('/storefront', function () {
    return redirect()->route('storefront');
});

Route::get('/storefront/search-suggestions', [StorefrontController::class, 'searchSuggestions'])
    ->name('storefront.search-suggestions');

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
