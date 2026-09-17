<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="W68 customer discounts by brand.">
    <title>W68 Autoparts | Discounts</title>
    <link rel="icon" href="{{ asset('build/assets/images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-orders.css') }}?v=20260915-v69">
    <link rel="stylesheet" href="{{ asset('css/w68-discounts.css') }}?v=20260915-v72">
    <link rel="stylesheet" href="{{ asset('css/w68-notifications.css') }}?v=20260915-v92">
    <script src="{{ asset('js/w68-discounts.js') }}?v=20260915-v93" defer></script>
    <script src="{{ asset('js/w68-notifications.js') }}?v=20260915-v92" defer></script>
</head>
<body
    data-cart-state-url="{{ route('home.cart.state') }}"
    data-cart-item-base-url="{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}{{ preg_replace('#/index\.php$#i', '', rtrim(str_replace('\\', '/', (string) request()->getBaseUrl()), '/')) }}/home/cart/items"
    data-cart-selection-url="{{ route('home.cart.selection') }}"
    data-notifications-url="{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}{{ preg_replace('#/index\.php$#i', '', rtrim(str_replace('\\', '/', (string) request()->getBaseUrl()), '/')) }}/home/notifications"
    data-notifications-read-all-url="{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}{{ preg_replace('#/index\.php$#i', '', rtrim(str_replace('\\', '/', (string) request()->getBaseUrl()), '/')) }}/home/notifications/read-all"
>
@php
    $logo = asset('build/assets/images/sidebar_logo.png');
    $initialCartCount = is_array($serverCart ?? null) ? count($serverCart) : 0;
@endphp

<header class="orders-header discounts-header">
    <div class="orders-header-inner">
        <a href="{{ route('home') }}" class="orders-brand">
            <img src="{{ $logo }}" alt="W68 Autoparts">
            <span><strong>W68 AUTOPARTS</strong><small>MY DISCOUNTS</small></span>
        </a>

        <div class="orders-header-actions discounts-header-actions">
            <a href="{{ route('home') }}" class="orders-home-button discounts-home-button" aria-label="Go to home">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
                <span>HOME</span>
            </a>

            <a href="{{ route('orders') }}" class="orders-home-button discounts-orders-button" aria-label="Open orders">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 4h14v16H5z"/>
                    <path d="M8 8h8M8 12h8M8 16h5"/>
                </svg>
                <span>ORDERS</span>
            </a>

            <button type="button" class="orders-cart-button discounts-cart-button" data-orders-cart-open aria-label="Open cart">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20.5 7H6.2M9.5 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg>
                <span>CART</span>
                <b data-orders-cart-count>{{ $initialCartCount }}</b>
            </button>

            <div class="orders-notification-slot" aria-label="Customer notifications">
                @include('partials.notification-bell')
            </div>
        </div>
    </div>
</header>

<main class="discounts-shell">
    <section class="discounts-hero">
        <div>
            <span class="discounts-kicker">CUSTOMER-SPECIFIC PRICING</span>
            <h1>Discounts by Brand</h1>
            <p>These are the brand discounts assigned specifically to your W68 customer account. Select a brand to open only that brand's products in the catalog.</p>
        </div>
        <div class="discounts-count-card">
            <strong>{{ $discountedBrandCount }}</strong>
            <span>DISCOUNTED BRANDS</span>
        </div>
    </section>

    <section class="discounts-section">
        <div class="discounts-section-heading">
            <div>
                <span>YOUR DISCOUNTS</span>
                <h2>Choose a Brand</h2>
            </div>
            <p>Only brands with an assigned discount greater than 0% are shown.</p>
        </div>

        <div class="discount-brand-grid">
            @forelse ($brands as $brand)
                <a
                    class="discount-brand-card"
                    href="{{ route('home', ['brand' => $brand['brand']]) }}#products"
                    aria-label="View {{ $brand['brand'] }} products with {{ rtrim(rtrim(number_format((float) $brand['discount'], 2, '.', ''), '0'), '.') }} percent discount"
                >
                    <div class="discount-brand-percent">
                        <strong>{{ rtrim(rtrim(number_format((float) $brand['discount'], 2, '.', ''), '0'), '.') }}%</strong>
                        <span>DISCOUNT</span>
                    </div>
                    <div class="discount-brand-copy">
                        <span>BRAND</span>
                        <h3>{{ $brand['brand'] }}</h3>
                        <small>VIEW PRODUCTS →</small>
                    </div>
                </a>
            @empty
                <div class="discounts-empty">
                    <strong>No brand discounts are currently assigned.</strong>
                    <span>Only customer-specific discounts greater than 0% appear on this page.</span>
                </div>
            @endforelse
        </div>
    </section>
</main>

<aside class="orders-cart-drawer" data-orders-cart-drawer aria-hidden="true">
    <button type="button" class="orders-cart-backdrop" data-orders-cart-close aria-label="Close cart"></button>
    <section class="orders-cart-panel" aria-label="Cart">
        <header class="orders-cart-header">
            <div><span>YOUR ORDER</span><h2>Cart</h2></div>
            <button type="button" class="orders-cart-close" data-orders-cart-close aria-label="Close cart">&times;</button>
        </header>
        <div class="orders-cart-select-row">
            <label><input type="checkbox" data-orders-cart-select-all> <span>SELECT ALL ITEMS</span></label>
            <b data-orders-cart-selected-count>0 selected</b>
        </div>
        <div class="orders-cart-items" data-orders-cart-items></div>
        <div class="orders-cart-empty" data-orders-cart-empty>Your cart is empty.</div>
        <footer class="orders-cart-footer">
            <div class="orders-cart-totals">
                <div><span>TOTAL</span><strong data-orders-cart-total>0.00</strong></div>
                <div><span>SELECTED TOTAL</span><strong data-orders-cart-selected-total>0.00</strong></div>
            </div>
            <a href="{{ route('home') }}" class="orders-cart-shop">GO TO SHOP</a>
        </footer>
    </section>
</aside>
@include('partials.notification-modal')
</body>
</html>
