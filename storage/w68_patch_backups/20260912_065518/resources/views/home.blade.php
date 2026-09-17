<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="W68 Autoparts & Service Center customer online pricelist.">
    <title>W68 Autoparts & Service Center | Home</title>
    <link rel="icon" href="{{ asset('build/assets/images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-home.css') }}?v=20260912-v47">
    <script src="{{ asset('js/w68-home.js') }}?v=20260912-v47" defer></script>
</head>
<body
    data-search-suggestion-url="{{ route('home.search-suggestions') }}"
    data-fallback-image="{{ asset('build/assets/images/sidebar_logo.png') }}"
>
    @php
        $logo = asset('build/assets/images/sidebar_logo.png');

        $hasActiveSearch = collect($filters)->contains(
            fn ($value) => trim((string) $value) !== ''
        );

        $productData = function ($product) {
            return [
                'id' => (string) $product->id,
                'image' => route('home.product-image', ['product' => $product->id]),
                'productCode' => (string) ($product->product_code ?? ''),
                'partNumber' => (string) ($product->part_number ?? ''),
                'description' => (string) ($product->description ?? ''),
                'application' => (string) ($product->application ?? ''),
                'specification' => (string) ($product->specification ?? ''),
                'position' => (string) ($product->position ?? ''),
                'brand' => (string) ($product->brand ?? ''),
                'price' => number_format((float) ($product->display_price ?? $product->selling_price ?? 0), 2, '.', ''),
            ];
        };

        $activeSummaryTerms = collect([
            'Product Code' => $filters['product_code'] ?? '',
            'Part No.' => $filters['part_number'] ?? '',
            'Description' => $filters['description'] ?? '',
            'Brand' => $filters['brand'] ?? '',
            'Application' => $filters['application'] ?? '',
            'Position' => $filters['position'] ?? '',
        ])->map(fn ($value) => trim((string) $value))
          ->filter();

        $resultTotal = method_exists($products, 'total') ? (int) $products->total() : count($products);
    @endphp

    {{-- W68 loading animation only — no background panel. --}}
    <div class="w68-loader" data-page-loader aria-hidden="false">
        <svg class="w68-loader-svg" viewBox="0 0 750 260" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="W68 loading">
            <defs>
                <linearGradient id="loaderMaroonGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#a31c3f" />
                    <stop offset="40%" stop-color="#800020" />
                    <stop offset="100%" stop-color="#4a0011" />
                </linearGradient>
                <linearGradient id="loaderGoldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#FFF5C0" />
                    <stop offset="30%" stop-color="#FFD700" />
                    <stop offset="70%" stop-color="#D4AF37" />
                    <stop offset="100%" stop-color="#996515" />
                </linearGradient>
            </defs>

            <g class="w68-loader-trace" stroke-linecap="round" stroke-linejoin="round">
                <path d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="#800020" stroke-width="16" fill="none" />
                <path d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="#d4af37" stroke-width="16" fill="none" />
                <path d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="#d4af37" stroke-width="16" fill="none" />
            </g>

            <g stroke-linecap="round" stroke-linejoin="round">
                <path data-loader-path data-loader-color="#800020" class="loader-maroon-glow" d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="url(#loaderMaroonGrad)" stroke-width="16" fill="none" />
                <path data-loader-path data-loader-color="#FFD700" class="loader-gold-glow" d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="url(#loaderGoldGrad)" stroke-width="16" fill="none" />
                <path data-loader-path data-loader-color="#FFD700" class="loader-gold-glow" d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="url(#loaderGoldGrad)" stroke-width="16" fill="none" />
            </g>

            <g data-loader-pen class="w68-loader-pen">
                <circle data-loader-aura cx="0" cy="0" r="14" fill="#FFD700" opacity="0.35" />
                <circle data-loader-dot cx="0" cy="0" r="6" fill="#FFFFFF" stroke="#FFD700" stroke-width="2.5" />
                <circle data-loader-sparkle-one cx="0" cy="0" r="2.5" fill="#FFF2B2" />
                <circle data-loader-sparkle-two cx="0" cy="0" r="2" fill="#D4AF37" />
            </g>
        </svg>
    </div>

    <header class="customer-nav" data-customer-nav>
        <div class="customer-nav-shell">
            <div class="nav-main-content" data-nav-main>
                <div class="nav-top-row">
                    <a class="nav-brand" href="{{ route('home') }}">
                        <img src="{{ $logo }}" alt="W68 Autoparts & Service Center">
                        <span>
                            <strong>W68 AUTOPARTS</strong>
                            <small>Service Center • Online Pricelist</small>
                        </span>
                    </a>

                    <a class="nav-new-items" href="{{ route('home') }}#new-items" data-loading-link>NEW ITEMS</a>

                    <div class="nav-account">
                        <a class="nav-profile-link" href="{{ route('settings') }}" data-loading-link aria-label="Open account settings">
                            <img
                                class="nav-profile-picture"
                                src="{{ route('home.profile-picture') }}?v={{ optional($account->updated_at)->timestamp ?? time() }}"
                                alt="{{ $profileName }} profile picture"
                            >
                            <span class="nav-profile-identity">
                                <strong>{{ $account->User_ID ?: $profileName }}</strong>
                                <small>{{ $account->Email ?: 'No email address' }}</small>
                            </span>
                        </a>

                        <form action="{{ route('logout') }}" method="POST" class="nav-logout-form" data-loading-form>
                            @csrf
                            <button type="submit" class="nav-logout">LOGOUT</button>
                        </form>
                    </div>
                </div>

                <form class="nav-search-form" action="{{ route('home') }}#products" method="GET" data-loading-form>
                    <div class="nav-search-toolbar">
                        <button
                            class="nav-cart-button nav-cart-button-inline"
                            type="button"
                            data-cart-open
                            aria-label="Open cart"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H6"/>
                                <circle cx="10" cy="20" r="1.4"/>
                                <circle cx="18" cy="20" r="1.4"/>
                            </svg>
                            <span>ADD TO CART</span>
                            <b data-cart-count>0</b>
                        </button>
                    </div>
                    <div class="nav-search-grid">
                        @foreach ([
                            ['product_code', 'Product Code', 'Search product code'],
                            ['part_number', 'Part Number', 'Search part number'],
                            ['description', 'Description', 'Search description'],
                            ['application', 'Application', 'Search application'],
                            ['brand', 'Brand', 'Search brand'],
                            ['position', 'Position', 'Search position'],
                        ] as [$field, $label, $placeholder])
                            <div class="nav-search-field recommendation-host">
                                <label for="nav-search-{{ str_replace('_', '-', $field) }}">{{ $label }}</label>
                                <input
                                    id="nav-search-{{ str_replace('_', '-', $field) }}"
                                    type="search"
                                    name="{{ $field }}"
                                    value="{{ $filters[$field] }}"
                                    placeholder="{{ $placeholder }}"
                                    autocomplete="off"
                                    data-recommend-input
                                    data-recommend-field="{{ $field }}"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                >
                                <div class="search-recommendations" data-recommend-list role="listbox" hidden></div>
                            </div>
                        @endforeach
                    </div>

                    <div class="nav-search-actions">
                        <button type="submit" class="nav-search-button">SEARCH</button>
                        <a href="{{ route('home') }}#products" class="nav-clear-button" data-loading-link>CLEAR</a>
                    </div>
                </form>
            </div>

            <div
                class="nav-collapsed-content"
                data-nav-collapsed-summary
                aria-hidden="true"
            >
                <button
                    type="button"
                    class="nav-collapsed-expand"
                    data-nav-expand-trigger
                    aria-label="Expand W68 navigation bar"
                >
                    <span class="collapsed-summary-copy">
                        <small>ACTIVE SEARCH</small>

                        <span class="collapsed-summary-values">
                            @if ($activeSummaryTerms->isNotEmpty())
                                @foreach ($activeSummaryTerms as $label => $term)
                                    <strong><span>{{ $label }}</span>{{ $term }}</strong>
                                @endforeach
                            @else
                                <strong class="summary-all"><span>Catalog</span>ALL SELECTED PRODUCTS</strong>
                            @endif
                        </span>

                        <em>{{ number_format($resultTotal) }} result{{ $resultTotal === 1 ? '' : 's' }}</em>
                    </span>

                    <span class="collapsed-click-hint">
                        <b>CLICK / TAP TO OPEN NAVIGATION</b>
                        <span>Open the search fields and continue your lookup.</span>
                    </span>
                </button>

                <button
                    class="nav-cart-button nav-cart-button-collapsed"
                    type="button"
                    data-cart-open
                    aria-label="Open cart"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H6"/>
                        <circle cx="10" cy="20" r="1.4"/>
                        <circle cx="18" cy="20" r="1.4"/>
                    </svg>
                    <b data-cart-count>0</b>
                </button>
            </div>
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="page-status shell">{{ session('status') }}</div>
        @endif

        @unless ($hasActiveSearch)
        <section class="new-items-section shell" id="new-items">
            <div class="section-heading">
                <div>
                    <span>JUST ADDED</span>
                    <h1>New Items</h1>
                </div>
                <p>Only products selected for the W68 website are shown.</p>
            </div>

            @if (count($newItems))
                <div class="new-items-track">
                    @foreach ($newItems as $product)
                        @php $data = $productData($product); @endphp

                        <article
                            class="new-item-card"
                            tabindex="0"
                            role="button"
                            data-product-card
                            data-product-id="{{ $data['id'] }}"
                            data-product-image="{{ $data['image'] }}"
                            data-product-product-code="{{ $data['productCode'] }}"
                            data-product-part-number="{{ $data['partNumber'] }}"
                            data-product-description="{{ $data['description'] }}"
                            data-product-application="{{ $data['application'] }}"
                            data-product-specification="{{ $data['specification'] }}"
                            data-product-position="{{ $data['position'] }}"
                            data-product-brand="{{ $data['brand'] }}"
                            data-product-price="{{ $data['price'] }}"
                        >
                            <img src="{{ $data['image'] }}" alt="{{ $data['description'] ?: $data['productCode'] }}" loading="lazy" onerror="this.onerror=null;this.src='{{ $logo }}'">

                            <div class="new-item-copy">
                                <small>{{ $data['productCode'] ?: 'W68 PRODUCT' }}</small>
                                <strong>{{ $data['description'] ?: 'W68 Product' }}</strong>
                                <span>{{ $data['brand'] ?: 'W68 Autoparts' }}</span>
                                <b>₱{{ number_format((float) $data['price'], 2) }}</b>
                            </div>

                            <div class="new-item-actions">
                                <button type="button" class="new-add-cart" data-add-cart>ADD TO CART</button>
                                <button type="button" class="new-view-cart" data-view-cart hidden>VIEW CART</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="empty-state">No selected new items are available right now.</div>
            @endif
        </section>
        @endunless

        <section class="products-section shell" id="products">
            @if ($hasActiveSearch)
                <div class="active-search-notice">
                    <strong>SEARCH RESULTS</strong>
                    <span>New Items are hidden while a search is active. Press CLEAR to return to All Products.</span>
                </div>
            @endif

            <div class="section-heading">
                <div>
                    <span>W68 WEBSITE CATALOG</span>
                    <h2>Products</h2>
                </div>

                @if (method_exists($products, 'total'))
                    <p>{{ number_format($products->total()) }} matching products • 100 per page</p>
                @endif
            </div>

            @if (count($products))
                <div class="product-grid">
                    @foreach ($products as $product)
                        @php $data = $productData($product); @endphp

                        <article
                            class="product-card"
                            id="product-{{ $product->id }}"
                            tabindex="0"
                            role="button"
                            aria-label="View {{ $data['description'] ?: $data['productCode'] }}"
                            data-product-card
                            data-product-id="{{ $data['id'] }}"
                            data-product-image="{{ $data['image'] }}"
                            data-product-product-code="{{ $data['productCode'] }}"
                            data-product-part-number="{{ $data['partNumber'] }}"
                            data-product-description="{{ $data['description'] }}"
                            data-product-application="{{ $data['application'] }}"
                            data-product-specification="{{ $data['specification'] }}"
                            data-product-position="{{ $data['position'] }}"
                            data-product-brand="{{ $data['brand'] }}"
                            data-product-price="{{ $data['price'] }}"
                        >
                            <div class="product-image-wrap">
                                <img src="{{ $data['image'] }}" alt="{{ $data['description'] ?: $data['productCode'] }}" loading="lazy" onerror="this.onerror=null;this.src='{{ $logo }}'">
                                <span class="product-badge">W68</span>
                                <span class="in-cart-badge">IN CART</span>
                            </div>

                            <div class="product-card-body">
                                <div class="product-code">{{ $data['productCode'] ?: 'NO PRODUCT CODE' }}</div>
                                <h3>{{ $data['description'] ?: 'W68 Product' }}</h3>

                                <div class="product-card-meta">
                                    <span><b>Part No.</b> {{ $data['partNumber'] ?: '—' }}</span>
                                    <span><b>Brand</b> {{ $data['brand'] ?: '—' }}</span>
                                    <span><b>Application</b> {{ $data['application'] ?: '—' }}</span>
                                </div>

                                <div class="product-card-footer">
                                    <div class="product-price">
                                        <small>Selling Price</small>
                                        <strong>₱{{ number_format((float) $data['price'], 2) }}</strong>
                                    </div>

                                    <div class="product-card-actions">
                                        <button type="button" class="product-add-cart" data-add-cart>ADD TO CART</button>
                                        <button type="button" class="product-view-cart" data-view-cart hidden>VIEW CART</button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if (method_exists($products, 'hasPages') && $products->hasPages())
                    @php
                        $currentPage = $products->currentPage();
                        $lastPage = $products->lastPage();
                        $pageStart = max(1, $currentPage - 2);
                        $pageEnd = min($lastPage, $currentPage + 2);
                    @endphp

                    <nav class="home-pagination" aria-label="Product pages">
                        @if ($products->onFirstPage())
                            <span class="page-link disabled">PREVIOUS</span>
                        @else
                            <a class="page-link" href="{{ $products->previousPageUrl() }}#products" data-loading-link>PREVIOUS</a>
                        @endif

                        @if ($pageStart > 1)
                            <a class="page-link" href="{{ $products->url(1) }}#products" data-loading-link>1</a>
                            @if ($pageStart > 2)<span class="page-gap">…</span>@endif
                        @endif

                        @for ($page = $pageStart; $page <= $pageEnd; $page++)
                            @if ($page === $currentPage)
                                <span class="page-link current">{{ $page }}</span>
                            @else
                                <a class="page-link" href="{{ $products->url($page) }}#products" data-loading-link>{{ $page }}</a>
                            @endif
                        @endfor

                        @if ($pageEnd < $lastPage)
                            @if ($pageEnd < $lastPage - 1)<span class="page-gap">…</span>@endif
                            <a class="page-link" href="{{ $products->url($lastPage) }}#products" data-loading-link>{{ $lastPage }}</a>
                        @endif

                        @if ($products->hasMorePages())
                            <a class="page-link" href="{{ $products->nextPageUrl() }}#products" data-loading-link>NEXT</a>
                        @else
                            <span class="page-link disabled">NEXT</span>
                        @endif
                    </nav>
                @endif
            @else
                <div class="empty-state">No selected W68 products matched your filters.</div>
            @endif
        </section>
    </main>


    <footer id="w68-home-footer" class="w68-home-footer">
        <div class="shell w68-home-footer-grid">
            <div class="home-footer-company">
                <img src="{{ $logo }}" alt="W68 Autoparts & Service Center">
                <div>
                    <strong>© w68Autoparts &amp; Service Center</strong>
                    <span>Ownership: Warren Yu</span>
                    <small>Customer Online Pricelist</small>
                </div>
            </div>

            <div class="home-footer-contact">
                <span class="home-footer-section-title">VISIT US</span>

                <a
                    class="home-footer-action"
                    href="https://www.google.com/maps/search/?api=1&query=48%20Timothy%20St.%20Multinational%20Village%20Paranaque%20City"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Open W68 address in Google Maps"
                >
                    <span class="home-footer-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                    </span>
                    <span class="home-footer-action-copy">
                        <b>48 Timothy St. Multinational Village Parañaque City</b>
                        <small>CLICK / TAP TO OPEN IN GOOGLE MAPS ↗</small>
                    </span>
                </a>
            </div>

            <div class="home-footer-contact">
                <span class="home-footer-section-title">CONTACT W68</span>

                <div class="home-footer-phone-grid">
                    <a class="home-footer-action" href="tel:+63285539092" aria-label="Call W68 at 8553-9092">
                        <span class="home-footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M7.3 3.5 10 7.8 8.2 9.7c1.1 2.2 2.9 4 5.1 5.1l1.9-1.8 4.3 2.7-.7 3.1c-.2.9-1 1.5-1.9 1.5C10 20.2 3.8 14 3.8 7.1c0-.9.6-1.7 1.5-1.9l2-.4Z"/></svg>
                        </span>
                        <span class="home-footer-action-copy">
                            <b>8553-9092</b>
                            <small>TEL. NO. • TAP TO CALL</small>
                        </span>
                    </a>

                    <a class="home-footer-action" href="tel:+63288290480" aria-label="Call W68 at 8829-0480">
                        <span class="home-footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M7.3 3.5 10 7.8 8.2 9.7c1.1 2.2 2.9 4 5.1 5.1l1.9-1.8 4.3 2.7-.7 3.1c-.2.9-1 1.5-1.9 1.5C10 20.2 3.8 14 3.8 7.1c0-.9.6-1.7 1.5-1.9l2-.4Z"/></svg>
                        </span>
                        <span class="home-footer-action-copy">
                            <b>8829-0480</b>
                            <small>TEL. NO. • TAP TO CALL</small>
                        </span>
                    </a>
                </div>

                <a class="home-footer-action" href="tel:+639173239605" aria-label="Call W68 mobile 0917-3239-605">
                    <span class="home-footer-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M10 5h4M11 18.5h2"/></svg>
                    </span>
                    <span class="home-footer-action-copy">
                        <b>0917-3239-605</b>
                        <small>MOBILE NO. • TAP TO CALL</small>
                    </span>
                </a>

                <a class="home-footer-action" href="viber://chat?number=%2B639498818468" aria-label="Open Viber chat with W68 0949-8818-468">
                    <span class="home-footer-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 4.5h14v11H9l-4 4v-15Z"/><path d="M9 8.5c1.1 2.3 2.2 3.4 4.6 4.5"/></svg>
                    </span>
                    <span class="home-footer-action-copy">
                        <b>0949-8818-468</b>
                        <small>MOBILE / VIBER • TAP TO OPEN VIBER</small>
                    </span>
                </a>
            </div>
        </div>
    </footer>

    {{-- Add to Cart side drawer --}}
    <aside class="cart-drawer" data-cart-drawer aria-hidden="true">
        <button class="cart-backdrop" type="button" data-cart-close aria-label="Close cart"></button>

        <section class="cart-panel" aria-label="Shopping cart">
            <header class="cart-header">
                <div>
                    <span>YOUR ORDER</span>
                    <h2>Add to Cart</h2>
                </div>
                <button type="button" class="cart-close" data-cart-close aria-label="Close cart">×</button>
            </header>

            <div class="cart-select-all-row">
                <label>
                    <input type="checkbox" data-cart-select-all>
                    <span>SELECT ALL ITEMS</span>
                </label>
                <b data-cart-selected-count>0 selected</b>
            </div>

            <div class="cart-items" data-cart-items></div>
            <div class="cart-empty" data-cart-empty>Your cart is empty.</div>

            <footer class="cart-footer cart-footer-v36">
                <div class="cart-footer-totals">
                    <div><span>Cart Total</span><strong data-cart-total>₱0.00</strong></div>
                    <div><span>Selected Total</span><strong data-cart-selected-total>₱0.00</strong></div>
                </div>
                <button type="button" class="process-order-button" data-process-order disabled>PROCESS ORDER</button>
            </footer>
        </section>
    </aside>

    {{-- Product view modal --}}
    <div class="product-modal" data-product-modal hidden aria-hidden="true">
        <button class="modal-backdrop" type="button" data-product-modal-close aria-label="Back"></button>
        <section class="detail-modal-card" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
            <div class="detail-modal-image-pane"><img data-product-modal-image src="{{ $logo }}" alt="Product"></div>
            <div class="detail-modal-content">
                <span class="detail-modal-kicker">W68 PRODUCT DETAILS</span>
                <h2 id="product-modal-title">View Product</h2>
                <div class="detail-list">
                    <div><span>Description</span><strong data-product-modal-description>—</strong></div>
                    <div><span>Product Code</span><strong data-product-modal-code>—</strong></div>
                    <div><span>Part No</span><strong data-product-modal-part>—</strong></div>
                    <div><span>Application</span><strong data-product-modal-application>—</strong></div>
                    <div><span>Specification</span><strong data-product-modal-specification>—</strong></div>
                    <div><span>Brand</span><strong data-product-modal-brand>—</strong></div>
                </div>
                <div class="modal-actions">
                    <span>ACTION</span>
                    <div>
                        <button type="button" class="modal-add-cart" data-product-modal-add>ADD TO CART</button>
                        <button type="button" class="modal-back" data-product-modal-close>BACK</button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Cart item full view --}}
    <div class="cart-item-modal" data-cart-item-modal hidden aria-hidden="true">
        <button class="modal-backdrop" type="button" data-cart-item-modal-close aria-label="Close"></button>
        <section class="detail-modal-card" role="dialog" aria-modal="true">
            <div class="detail-modal-image-pane"><img data-cart-item-modal-image src="{{ $logo }}" alt="Product"></div>
            <div class="detail-modal-content">
                <span class="detail-modal-kicker">ITEM IN YOUR CART</span>
                <h2>Cart Item Details</h2>
                <div class="detail-list">
                    <div><span>Description</span><strong data-cart-item-modal-description>—</strong></div>
                    <div><span>Product Code</span><strong data-cart-item-modal-code>—</strong></div>
                    <div><span>Part No</span><strong data-cart-item-modal-part>—</strong></div>
                    <div><span>Application</span><strong data-cart-item-modal-application>—</strong></div>
                    <div><span>Specification</span><strong data-cart-item-modal-specification>—</strong></div>
                    <div><span>Brand</span><strong data-cart-item-modal-brand>—</strong></div>
                </div>
                <div class="cart-order-summary">
                    <div><span>Price</span><strong data-cart-item-modal-price>₱0.00</strong></div>
                    <div><span>Ordered Qty</span><strong data-cart-item-modal-qty>0</strong></div>
                    <div><span>Total</span><strong data-cart-item-modal-total>₱0.00</strong></div>
                </div>
                <div class="modal-actions compact">
                    <span>ACTION</span>
                    <div><button type="button" class="modal-back" data-cart-item-modal-close>CLOSE</button></div>
                </div>
            </div>
        </section>
    </div>

    {{-- Quantity chooser shown BEFORE an item is added to cart. --}}
    <div class="quantity-modal" data-quantity-modal hidden aria-hidden="true">
        <button class="modal-backdrop" type="button" data-quantity-modal-close aria-label="Cancel"></button>
        <section class="quantity-modal-card" role="dialog" aria-modal="true" aria-labelledby="quantity-modal-title">
            <img data-quantity-modal-image src="{{ $logo }}" alt="Product">
            <div class="quantity-modal-copy">
                <span>SET ORDERED QTY</span>
                <h2 id="quantity-modal-title" data-quantity-modal-description>W68 Product</h2>
                <div class="quantity-product-meta"><b data-quantity-modal-code>—</b><strong data-quantity-modal-price>₱0.00</strong></div>

                <label class="quantity-label">Ordered Qty</label>
                <div class="quantity-stepper">
                    <button type="button" data-quantity-minus>−</button>
                    <input type="number" min="1" max="9999" value="1" inputmode="numeric" data-quantity-input>
                    <button type="button" data-quantity-plus>+</button>
                </div>

                <div class="quantity-total"><span>Total</span><strong data-quantity-modal-total>₱0.00</strong></div>
                <div class="quantity-actions">
                    <button type="button" class="quantity-confirm" data-quantity-confirm>ADD TO CART</button>
                    <button type="button" class="quantity-cancel" data-quantity-modal-close>CANCEL</button>
                </div>
            </div>
        </section>
    </div>

    {{-- Process order reminder / agreement modal. --}}
    <div class="process-order-modal" data-process-order-modal hidden aria-hidden="true">
        <button class="modal-backdrop" type="button" data-process-order-close aria-label="Cancel"></button>
        <section class="process-order-card" role="dialog" aria-modal="true" aria-labelledby="process-order-title">
            <div class="process-order-icon">!</div>
            <span>ORDER FINALIZATION REMINDER</span>
            <h2 id="process-order-title">Review Before Proceeding</h2>
            <p>
                The items you selected are about to be finalized. Please confirm the item quantities and totals before proceeding.
                After you proceed, the selected items will be removed from this cart.
            </p>
            <div class="process-order-summary">
                <div><span>Selected Items</span><strong data-process-order-count>0</strong></div>
                <div><span>Selected Total</span><strong data-process-order-total>₱0.00</strong></div>
            </div>
            <label class="process-agreement">
                <input type="checkbox" data-process-agree>
                <span>I reviewed the selected items and agree to finalize them.</span>
            </label>
            <div class="process-order-actions">
                <button type="button" class="process-proceed" data-process-proceed disabled>PROCEED</button>
                <button type="button" class="process-cancel" data-process-order-close>CANCEL</button>
            </div>
        </section>
    </div>

    {{-- 2-second Add to Cart message --}}
    <div class="cart-message-modal" data-cart-message hidden aria-hidden="true">
        <div class="cart-message-card">
            <div class="cart-message-check">✓</div>
            <strong>ADDED TO CART</strong>
            <span data-cart-message-text>Item added successfully.</span>
        </div>
    </div>

    {{-- 2-second order success message --}}
    <div class="order-success-modal" data-order-success hidden aria-hidden="true">
        <div class="cart-message-card">
            <div class="cart-message-check">✓</div>
            <strong>ORDER PROCESSED</strong>
            <span>Selected cart items were finalized and removed from your cart.</span>
        </div>
    </div>
</body>
</html>
