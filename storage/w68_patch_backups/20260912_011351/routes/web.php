<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| W68 Default Page
|--------------------------------------------------------------------------
| Root URL opens LOGIN only.
|
| http://localhost/w68_Pricelist/public/
|        -> /login
|
| The storefront remains available only when its URL is explicitly opened:
| http://localhost/w68_Pricelist/public/storefront
*/
Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/
Route::get('/storefront', [StorefrontController::class, 'index'])
    ->name('storefront');

Route::get('/storefront/search-suggestions', [StorefrontController::class, 'searchSuggestions'])
    ->name('storefront.search-suggestions');

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/register', [LoginController::class, 'register'])->name('register.attempt');
Route::post('/otp/verify', [LoginController::class, 'verifyOtp'])->name('otp.verify');
Route::post('/otp/resend', [LoginController::class, 'resendOtp'])->name('otp.resend');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
