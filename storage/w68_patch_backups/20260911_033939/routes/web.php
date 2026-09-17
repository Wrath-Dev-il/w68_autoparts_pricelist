<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('storefront');
});

Route::get('/storefront', [StorefrontController::class, 'index'])->name('storefront');
Route::get('/storefront/image/{id}', [StorefrontController::class, 'transparentImage'])
    ->whereNumber('id')
    ->name('storefront.transparent-image');
