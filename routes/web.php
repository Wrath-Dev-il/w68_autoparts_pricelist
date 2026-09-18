<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuthorizedAccessController;
use App\Http\Controllers\CustomerHomeController;
use App\Http\Controllers\CustomerNotificationController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\CustomerSettingsController;
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
| Customer Authorization Entry
|--------------------------------------------------------------------------
*/
Route::get('/authorized-access/{token}', AuthorizedAccessController::class)
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('authorized-access');

/*
|--------------------------------------------------------------------------
| Account Type 5 Customer Home
|--------------------------------------------------------------------------
*/
Route::middleware(['portal.access', 'auth'])->group(function () {
    Route::get('/home', [CustomerHomeController::class, 'index'])
        ->name('home');

    Route::get('/home/search-suggestions', [CustomerHomeController::class, 'searchSuggestions'])
        ->name('home.search-suggestions');

    Route::get('/home/product-image/{product}', [CustomerHomeController::class, 'productImage'])
        ->whereNumber('product')
        ->name('home.product-image');

    Route::get('/home/profile-picture', [CustomerHomeController::class, 'profilePicture'])
        ->name('home.profile-picture');

    Route::get('/home/notifications', [CustomerNotificationController::class, 'index'])
        ->name('home.notifications');

    // W68_PRICELIST_SOA_NOTIFICATION_20260918
    Route::post('/home/notifications/{notification}/read', [CustomerNotificationController::class, 'markRead'])
        ->where('notification', '(?:order:|soa:)?[0-9]+')
        ->name('home.notifications.read');

    Route::post('/home/notifications/read-all', [CustomerNotificationController::class, 'markAllRead'])
        ->name('home.notifications.read-all');


    Route::get('/discounts', [CustomerHomeController::class, 'discounts'])
        ->name('discounts');

    Route::get('/settings', [CustomerSettingsController::class, 'index'])
        ->name('settings');

    Route::post('/settings/profile-picture', [CustomerSettingsController::class, 'updateProfilePicture'])
        ->name('settings.profile-picture.update');

    Route::post('/settings/password/request', [CustomerSettingsController::class, 'requestPasswordChange'])
        ->name('settings.password.request');

    Route::post('/settings/password/confirm', [CustomerSettingsController::class, 'confirmPasswordChange'])
        ->name('settings.password.confirm');


    Route::get('/home/cart/state', [CustomerHomeController::class, 'cartState'])
        ->name('home.cart.state');

    Route::post('/home/cart/items', [CustomerHomeController::class, 'cartAdd'])
        ->name('home.cart.add');

    Route::patch('/home/cart/items/{product}', [CustomerHomeController::class, 'cartUpdate'])
        ->whereNumber('product')
        ->name('home.cart.update');

    Route::delete('/home/cart/items/{product}', [CustomerHomeController::class, 'cartRemove'])
        ->whereNumber('product')
        ->name('home.cart.remove');

    Route::put('/home/cart/selection', [CustomerHomeController::class, 'cartSelection'])
        ->name('home.cart.selection');

    Route::delete('/home/cart/selected', [CustomerHomeController::class, 'cartRemoveSelected'])
        ->name('home.cart.remove-selected');

    Route::put('/home/cart/sync', [CustomerHomeController::class, 'cartSync'])
        ->name('home.cart.sync');


    Route::get('/process-order', [CustomerOrderController::class, 'processOrderPage'])
        ->name('process-order');

    Route::get('/process-order/{order}', [CustomerOrderController::class, 'viewOrder'])
        ->whereNumber('order')
        ->name('orders.view');

    Route::get('/process-order/{order}/return/{return}', [CustomerOrderController::class, 'viewReturn'])
        ->whereNumber('order')
        ->whereNumber('return')
        ->name('orders.return.view');

    Route::post('/home/orders/process', [CustomerOrderController::class, 'processSelectedCart'])
        ->name('home.orders.process');

    Route::get('/orders', [CustomerOrderController::class, 'index'])
        ->name('orders');

    Route::patch('/orders/{order}', [CustomerOrderController::class, 'updateOrder'])
        ->whereNumber('order')
        ->name('orders.update');

    Route::delete('/orders/{order}/items/{item}', [CustomerOrderController::class, 'deleteItem'])
        ->whereNumber('order')
        ->whereNumber('item')
        ->name('orders.items.delete');

    Route::delete('/orders/{order}', [CustomerOrderController::class, 'deleteOrder'])
        ->whereNumber('order')
        ->name('orders.delete');
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
| Authentication - authorization link/QR required
|--------------------------------------------------------------------------
*/
Route::middleware('portal.access')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::post('/forgot-password', [LoginController::class, 'forgotPassword'])->name('password.forgot');
    Route::get('/reset-password', [LoginController::class, 'showResetPassword'])->name('password.reset.form');
    Route::post('/reset-password', [LoginController::class, 'resetPassword'])->name('password.reset.update');
    Route::post('/register', [LoginController::class, 'register'])->name('register.attempt');
    Route::post('/otp/verify', [LoginController::class, 'verifyOtp'])->name('otp.verify');
    Route::post('/otp/resend', [LoginController::class, 'resendOtp'])->name('otp.resend');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
