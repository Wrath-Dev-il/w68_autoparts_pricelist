<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerHomeController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| W68 Entry
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    $account = Auth::user();

    if ($account && (int) ($account->account_type ?? 0) === 5) {
        return redirect()->route('home');
    }

    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Account Type 5 Customer Home
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/home', [CustomerHomeController::class, 'index'])
        ->name('home');

    Route::get('/home/search-suggestions', [CustomerHomeController::class, 'searchSuggestions'])
        ->name('home.search-suggestions');

    Route::get('/home/product-image/{product}', [CustomerHomeController::class, 'productImage'])
        ->whereNumber('product')
        ->name('home.product-image');

    Route::get('/home/profile-picture', [CustomerHomeController::class, 'profilePicture'])
        ->name('home.profile-picture');
});

/*
|--------------------------------------------------------------------------
| Existing Storefront
|--------------------------------------------------------------------------
*/
Route::get('/storefront', [StorefrontController::class, 'index'])
    ->name('storefront');

Route::get('/storefront/search-suggestions', [StorefrontController::class, 'searchSuggestions'])
    ->name('storefront.search-suggestions');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/register', [LoginController::class, 'register'])->name('register.attempt');
Route::post('/otp/verify', [LoginController::class, 'verifyOtp'])->name('otp.verify');
Route::post('/otp/resend', [LoginController::class, 'resendOtp'])->name('otp.resend');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
