<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="W68 Autoparts online price list and parts catalog.">
    <title>W68 Autoparts | Online Pricelist</title>
    <link rel="icon" href="{{ asset('build/assets/images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-storefront.css') }}?v=20260911-v12">
</head>
<body>
    <header class="market-header">
        <div class="top-strip">
            <div class="shell top-strip-inner">
                <div class="top-links">
                    <span>W68 Autoparts</span>
                    <span class="separator">|</span>
                    <span>Auto Parts • Wholesale • Retail</span>
                </div>
                <div class="top-links top-links-right">
                    <a href="#brands">Brands</a>
                    <a href="#hero-new-items">New Items</a>
                    <a href="#products">Products</a>
                    <span class="separator">|</span>
                    <a class="auth-link login-link" href="{{ url('/login') }}">Login</a>
                    <a class="auth-link signup-link" href="{{ url('/register') }}">Sign Up</a>
                </div>
            </div>
        </div>

        <div class="shell main-header">
            <a class="brand" href="{{ route('storefront') }}" aria-label="W68 Autoparts home">
                <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="W68 Autoparts logo">
                <span class="brand-copy">
                    <strong>W68 AUTOPARTS</strong>
                    <small>Online Pricelist</small>
                </span>
            </a>

            <div class="search-zone">
                <form action="{{ route('storefront') }}" method="GET" class="search-form" data-nav-search-form data-suggestions-url="{{ route('storefront.search-suggestions') }}">
                    @if ($brand !== '')<input type="hidden" name="brand" value="{{ $brand }}">@endif
                    @if ($description !== '')<input type="hidden" name="description" value="{{ $description }}">@endif
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Search description, brand, product code, part number, application..."
                        autocomplete="off"
                        data-nav-search-input
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="nav-search-suggestions"
                    >
                    <button type="submit" aria-label="Search">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                        <span>Search</span>
                    </button>
                    <div class="search-suggestions" id="nav-search-suggestions" data-search-suggestions role="listbox" hidden></div>
                </form>
                <div class="quick-searches">
                    <span>Popular:</span>
                    <a href="{{ route('storefront', ['q' => 'gasket']) }}">Gasket</a>
                    <a href="{{ route('storefront', ['q' => 'bushing']) }}">Bushing</a>
                    <a href="{{ route('storefront', ['q' => 'Toyota']) }}">Toyota</a>
                    <a href="{{ route('storefront', ['q' => 'Isuzu']) }}">Isuzu</a>
                    <a href="{{ route('storefront', ['q' => 'Mitsubishi']) }}">Mitsubishi</a>
                </div>
            </div>

            <button class="cart-button" type="button" data-cart-open aria-label="Open cart">
                <span class="cart-icon-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 1.9-1.4L21 7H7M9 20h.01M17 20h.01"/></svg>
                    <span class="cart-count" data-cart-count>0</span>
                </span>
                <span>Cart</span>
            </button>
        </div>
    </header>

    <main>
        <section class="shell hero-grid">
            <div class="hero-main hero-product-carousel" id="hero-new-items" data-slide-carousel data-interval="2000" data-no-pause="true">
                <div class="hero-product-slides">
                    {{-- Slide 1: always show the W68 logo first --}}
                    <article class="hero-product-slide hero-logo-slide active" data-slide>
                        <div class="hero-logo-slide-copy">
                            <span class="eyebrow">W68 AUTOPARTS</span>
                            <h1>Online Pricelist</h1>
                            <p>Find the right auto part from the W68 catalog.</p>
                            <div class="hero-actions">
                                <a class="primary-action" href="#products">Browse Products</a>
                                <a class="secondary-action" href="#products">Shop Parts</a>
                            </div>
                        </div>
                        <div class="hero-logo-slide-visual">
                            <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="W68 Autoparts">
                        </div>
                    </article>

                    @foreach ($newItems as $product)
                        <article class="hero-product-slide" data-slide>
                            <div class="hero-product-copy hero-product-copy-minimal">
                                <span class="eyebrow">NEW ITEM {{ date('Y') }}</span>

                                <div class="hero-minimal-field">
                                    <span>Description</span>
                                    <h1>{{ $product->display_description ?: $product->name }}</h1>
                                </div>

                                <div class="hero-minimal-meta">
                                    <div>
                                        <span>Part Number</span>
                                        <strong>{{ $product->part_number ?: '—' }}</strong>
                                    </div>
                                    <div>
                                        <span>Brand</span>
                                        <strong>{{ $product->display_category ?: 'W68' }}</strong>
                                    </div>
                                </div>

                                <div class="hero-product-bottom hero-product-bottom-minimal">
                                    <div class="hero-price-block">
                                        <span>Price</span>
                                        <strong class="hero-product-price">₱{{ number_format((float) $product->display_price, 2) }}</strong>
                                    </div>
                                </div>

                                <div class="hero-actions">
                                    <a class="primary-action" href="#products">Browse Products</a>
                                    <a class="secondary-action" href="#products">Shop Parts</a>
                                </div>
                            </div>

                            <div class="hero-product-visual">
                                <div class="hero-logo-watermark" aria-hidden="true">
                                    <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="">
                                </div>
                                <div class="hero-product-image-wrap white-bg-blend">
                                    <img
                                        class="hero-product-image"
                                        src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="
                                        data-hero-src="{{ $product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}"
                                        alt="{{ $product->display_description ?: $product->name }}"
                                        loading="lazy"
                                        onerror="this.onerror=null; this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'"
                                    >
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if (count($newItems))
                    <div class="hero-dots" data-slide-dots aria-label="New item carousel">
                        <button type="button" class="hero-dot" data-slide-dot-action="prev" aria-label="Previous slide"></button>
                        <button type="button" class="hero-dot active" data-slide-dot-action="current" aria-label="Current slide" aria-current="true"></button>
                        <button type="button" class="hero-dot" data-slide-dot-action="next" aria-label="Next slide"></button>
                    </div>
                @endif
            </div>

            <div class="hero-side">
                <article class="promo-card top-seller-card" data-slide-carousel data-interval="2000">
                    <span>MOST SOLD BY BRAND</span>
                    @if (count($topCategorySellers))
                        <div class="top-seller-slides">
                            @foreach ($topCategorySellers as $index => $seller)
                                <div class="top-seller-slide {{ $index === 0 ? 'active' : '' }}" data-slide>
                                    <a class="hero-side-product-link" href="{{ route('storefront', array_filter([
                                            'product_code' => $seller->product->display_product_code ?? '',
                                            'description_search' => $seller->product->display_description ?? '',
                                            'brand_search' => $seller->product->display_category ?? '',
                                            'application' => $seller->product->application ?? '',
                                            'part_number' => $seller->product->part_number ?? '',
                                            'position' => $seller->product->position ?? '',
                                        ])) }}#products" title="View {{ $seller->product->name }}">
                                        <small class="top-seller-category">{{ $seller->category }}</small>
                                        <div class="top-seller-product">
                                            <img src="{{ $seller->product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $seller->product->name }}" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                                            <div>
                                                <strong>{{ $seller->product->name }}</strong>
                                                @if (!empty($seller->product->display_product_code))
                                                    <small>Code: {{ $seller->product->display_product_code }}</small>
                                                @endif
                                                @if ($seller->product->part_number)<small>Part No. {{ $seller->product->part_number }}</small>@endif
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        @if (count($topCategorySellers) > 1)
                            <div class="top-seller-nav">
                                <button type="button" data-slide-prev aria-label="Previous top seller">‹</button>
                                <span>Top 1 product in each brand</span>
                                <button type="button" data-slide-next aria-label="Next top seller">›</button>
                            </div>
                        @endif
                    @else
                        <strong>No qualifying sales yet</strong>
                        <small>Top sellers will appear here from qualifying OUT transactions in core4_ledger.</small>
                    @endif
                </article>

                <article class="promo-card fast-lookup-card">
                    <span>FAST LOOKUP</span>
                    @if ($fastLookup)
                        <a class="fast-lookup-product hero-side-product-link" href="{{ route('storefront', array_filter([
                            'product_code' => $fastLookup->display_product_code ?? '',
                            'description_search' => $fastLookup->display_description ?? '',
                            'brand_search' => $fastLookup->display_category ?? '',
                            'application' => $fastLookup->application ?? '',
                            'part_number' => $fastLookup->part_number ?? '',
                            'position' => $fastLookup->position ?? '',
                        ])) }}#products" title="View {{ $fastLookup->name }}">
                            <img src="{{ $fastLookup->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $fastLookup->name }}" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                            <div>
                                <strong>{{ $fastLookup->name }}</strong>
                                @if (!empty($fastLookup->display_product_code))
                                    <small>Code: {{ $fastLookup->display_product_code }}</small>
                                @endif
                                @if ($fastLookup->part_number)<small>Part No. {{ $fastLookup->part_number }}</small>@endif
                                @if ($fastLookup->application)<small>{{ $fastLookup->application }}</small>@endif
                                @if ($fastLookup->display_description)<small>{{ $fastLookup->display_description }}</small>@endif
                            </div>
                        </a>
                    @else
                        <strong>Search by part no.</strong>
                        <small>Product code • application • description</small>
                    @endif
                </article>
            </div>
        </section>

        <section class="shell service-strip" aria-label="Catalog highlights">
            <div><strong>{{ number_format($stats['products']) }}</strong><span>Products</span></div>
            <div><strong>{{ number_format($stats['brands']) }}</strong><span>Brands</span></div>
            <div><strong>{{ number_format($stats['car_brands']) }}</strong><span>Car Brand (Description)</span></div>
            <div><strong>W68</strong><span>Autoparts Catalog</span></div>
        </section>

        <section class="shell marketplace-section" id="brands">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">DISCOVER</span>
                    <h2>Shop by Brand</h2>
                    <p>Every brand/category currently available in the W68 catalog.</p>
                </div>
                @if ($brand !== '')
                    <a class="view-all" href="{{ route('storefront', array_filter(['q' => $search, 'description' => $description])) }}">Clear brand</a>
                @endif
            </div>

            <div class="brand-carousel" data-carousel data-interval="2000">
                <button class="carousel-arrow previous" type="button" data-carousel-prev aria-label="Previous brand">‹</button>
                <div class="carousel-viewport" data-carousel-viewport>
                    <div class="brand-track" data-carousel-track>
                        <a class="brand-card {{ $brand === '' ? 'active' : '' }}" href="{{ route('storefront', array_filter(['q' => $search, 'description' => $description])) }}">
                            <span class="brand-icon">ALL</span>
                            <strong>All Brands</strong>
                            <small>{{ number_format($stats['products']) }} products</small>
                            <em>Browse the full catalog</em>
                        </a>
                        @foreach ($brands as $item)
                            <a class="brand-card {{ $brand === $item->name ? 'active' : '' }}" href="{{ route('storefront', array_filter(['q' => $search, 'brand' => $item->name, 'description' => $description])) }}">
                                <span class="brand-icon">{{ strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $item->name), 0, 2)) ?: 'W8' }}</span>
                                <strong>{{ $item->name }}</strong>
                                <small>{{ number_format($item->total) }} products</small>
                                @if ($item->top_seller)
                                    <em title="{{ $item->top_seller->name }}">Most sellable: {{ $item->top_seller->name }}</em>
                                @else
                                    <em>No qualifying sales yet</em>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
                <button class="carousel-arrow next" type="button" data-carousel-next aria-label="Next brand">›</button>
            </div>
        </section>

        <section class="shell marketplace-section description-section" id="descriptions">
            <div class="section-heading compact-heading">
                <div>
                    <span class="section-kicker">DISCOVER</span>
                    <h2>Browse by Description</h2>
                    <p>Choose from every description currently used in the W68 catalog.</p>
                </div>
                @if ($description !== '')
                    <a class="view-all" href="{{ route('storefront', array_filter(['q' => $search, 'brand' => $brand, 'application' => $applicationSearch, 'part_number' => $partNumberSearch, 'position' => $positionSearch])) }}#descriptions">Clear description</a>
                @endif
            </div>

            <div class="brand-carousel description-carousel" data-carousel data-interval="2000">
                <button class="carousel-arrow previous" type="button" data-carousel-prev aria-label="Previous description">‹</button>
                <div class="carousel-viewport" data-carousel-viewport>
                    <div class="brand-track" data-carousel-track>
                        <a class="brand-card description-card {{ $description === '' ? 'active' : '' }}" href="{{ route('storefront', array_filter(['q' => $search, 'brand' => $brand, 'application' => $applicationSearch, 'part_number' => $partNumberSearch, 'position' => $positionSearch])) }}#descriptions">
                            <span class="brand-icon">ALL</span>
                            <strong>All Descriptions</strong>
                            <small>{{ number_format($stats['products']) }} products</small>
                            <em>Browse every description</em>
                        </a>

                        @foreach ($descriptions as $itemDescription)
                            <a class="brand-card description-card {{ $description === $itemDescription->name ? 'active' : '' }}" href="{{ route('storefront', array_filter(['q' => $search, 'brand' => $brand, 'description' => $itemDescription->name, 'application' => $applicationSearch, 'part_number' => $partNumberSearch, 'position' => $positionSearch])) }}#products">
                                <span class="brand-icon">{{ strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $itemDescription->name), 0, 2)) ?: 'DS' }}</span>
                                <strong title="{{ $itemDescription->name }}">{{ $itemDescription->name }}</strong>
                                <small>{{ number_format($itemDescription->total) }} products</small>
                                <em>View matching products</em>
                            </a>
                        @endforeach
                    </div>
                </div>
                <button class="carousel-arrow next" type="button" data-carousel-next aria-label="Next description">›</button>
            </div>
        </section>

        <section class="product-zone" id="products">
            <div class="shell marketplace-section products-section">
                <div class="section-heading product-heading">
                    <div>
                        <span class="section-kicker">W68 CATALOG</span>
                        <h2>{{ $search !== '' || $brand !== '' || $description !== '' || $descriptionSearch !== '' || $brandSearch !== '' || $productCodeSearch !== '' || $applicationSearch !== '' || $partNumberSearch !== '' || $positionSearch !== '' ? 'Filtered Products' : 'Recommended for You' }}</h2>
                        @if ($search !== '')<p>General search: “{{ $search }}”</p>@endif
                    </div>
                </div>

                <form class="catalog-toolbar" action="{{ route('storefront') }}" method="GET">
                    @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    @if ($brand !== '')<input type="hidden" name="brand" value="{{ $brand }}">@endif
                    @if ($description !== '')<input type="hidden" name="description" value="{{ $description }}">@endif

                    <div class="toolbar-field sort-field">
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort">
                            <option value="latest" @selected($sort === 'latest')>Latest</option>
                            <option value="price_low" @selected($sort === 'price_low')>Price: Low to High</option>
                            <option value="price_high" @selected($sort === 'price_high')>Price: High to Low</option>
                        </select>
                    </div>

                    <div class="toolbar-field">
                        <label for="product_code">Product Code</label>
                        <input id="product_code" type="search" name="product_code" value="{{ $productCodeSearch }}" placeholder="Search product code">
                    </div>

                    <div class="toolbar-field">
                        <label for="description_search">Description</label>
                        <input id="description_search" type="search" name="description_search" value="{{ $descriptionSearch }}" placeholder="Search description">
                    </div>

                    <div class="toolbar-field">
                        <label for="brand_search">Brand</label>
                        <input id="brand_search" type="search" name="brand_search" value="{{ $brandSearch }}" placeholder="Search brand">
                    </div>

                    <div class="toolbar-field">
                        <label for="application">Application</label>
                        <input id="application" type="search" name="application" value="{{ $applicationSearch }}" placeholder="Search application">
                    </div>

                    <div class="toolbar-field">
                        <label for="part_number">Part Number</label>
                        <input id="part_number" type="search" name="part_number" value="{{ $partNumberSearch }}" placeholder="Search part number">
                    </div>

                    <div class="toolbar-field">
                        <label for="position">Position</label>
                        <input id="position" type="search" name="position" value="{{ $positionSearch }}" placeholder="Search position">
                    </div>

                    <div class="toolbar-actions">
                        <button type="submit" class="toolbar-search">Search</button>
                        <a class="toolbar-clear" href="{{ route('storefront') }}#products">Clear</a>
                    </div>
                </form>

                @if (count($products))
                    <div class="product-grid">
                        @foreach ($products as $product)
                            @php $price = (float) $product->display_price; @endphp
                            <article
                                class="product-card"
                                id="product-{{ $product->id }}"
                                tabindex="0"
                                role="button"
                                aria-label="View details for {{ $product->name }}"
                                data-product-card
                                data-product-id="{{ $product->id }}"
                                data-product-name="{{ $product->name }}"
                                data-product-description="{{ $product->display_description }}"
                                data-product-brand="{{ $product->display_category }}"
                                data-product-part-number="{{ $product->part_number }}"
                                data-product-application="{{ $product->application }}"
                                data-product-position="{{ $product->position }}"
                                data-product-price="{{ number_format($price, 2, '.', '') }}"
                                data-product-image="{{ $product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}"
                                data-product-code="{{ $product->display_product_code }}"
                                data-product-specification="{{ $product->specification }}"
                            >
                                <div class="product-image-wrap">
                                    <img src="{{ $product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $product->name }}" loading="lazy" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                                    <span class="w68-badge">W68</span>
                                </div>
                                <div class="product-info">
                                    <div class="product-card-identifiers">
                                        <div class="product-identifier-row">
                                            <span>Product Code</span>
                                            <strong>{{ $product->display_product_code ?: '—' }}</strong>
                                        </div>
                                        <div class="product-identifier-row">
                                            <span>Specification</span>
                                            <strong>{{ $product->specification ?: '—' }}</strong>
                                        </div>
                                    </div>
                                    <h3 title="{{ $product->name }}">{{ $product->name }}</h3>
                                    @if ($product->part_number)
                                        <div class="part-number">Part No: {{ $product->part_number }}</div>
                                    @endif
                                    <div class="product-meta">
                                        <span>{{ $product->display_category }}</span>
                                        @if ($product->display_description)<span>{{ $product->display_description }}</span>@endif
                                        @if ($product->application)<span>{{ $product->application }}</span>@endif
                                        @if ($product->position)<span>{{ $product->position }}</span>@endif
                                    </div>
                                    <div class="product-card-bottom">
                                        <div class="price-wrap">
                                            <span class="currency">₱</span><strong>{{ number_format($price, 2) }}</strong>
                                        </div>
                                        <button type="button" class="add-cart" data-add-cart data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $price }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if (method_exists($products, 'links'))
                        <div class="pagination-wrap">
                            @if ($products->onFirstPage())
                                <span class="page-button disabled">← Previous</span>
                            @else
                                <a class="page-button" href="{{ $products->previousPageUrl() }}">← Previous</a>
                            @endif
                            <span class="page-status">Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
                            @if ($products->hasMorePages())
                                <a class="page-button" href="{{ $products->nextPageUrl() }}">Next →</a>
                            @else
                                <span class="page-button disabled">Next →</span>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="empty-state">
                        <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="W68">
                        <h3>No products found</h3>
                        <p>Try another product code, part number, description, application, or position.</p>
                        <a href="{{ route('storefront') }}">View all products</a>
                    </div>
                @endif
            </div>
        </section>
    </main>

    <footer id="footer">
        <div class="shell w68-contact-footer">
            <div class="footer-company">
                <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="W68 Autoparts">
                <div>
                    <strong>© w68Autoparts &amp; Service Center</strong>
                    <span>Ownership: Warren Yu</span>
                </div>
            </div>

            <div class="footer-contact-block">
                <strong class="footer-section-title">Address</strong>
                <a
                    class="footer-action-link footer-map-link"
                    href="https://www.google.com/maps/search/?api=1&query=48%20Timothy%20St.%20Multinational%20Village%20Paranaque%20City"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Open W68 Autoparts address in Google Maps"
                >
                    <span class="footer-action-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>
                    </span>
                    <span class="footer-action-copy">
                        <b>48 Timothy St. Multinational Village Parañaque City</b>
                        <small>Open in Google Maps ↗</small>
                    </span>
                </a>
            </div>

            <div class="footer-contact-block">
                <strong class="footer-section-title">Customer Support</strong>

                <div class="footer-phone-pair">
                    <a class="footer-action-link" href="tel:+63285539092" aria-label="Call W68 at 8553-9092">
                        <span class="footer-action-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M7.3 3.5 10 7.8 8.2 9.7c1.1 2.2 2.9 4 5.1 5.1l1.9-1.8 4.3 2.7-.7 3.1c-.2.9-1 1.5-1.9 1.5C10 20.2 3.8 14 3.8 7.1c0-.9.6-1.7 1.5-1.9l2-.4Z"/></svg>
                        </span>
                        <span class="footer-action-copy"><b>8553-9092</b><small>Tap to call</small></span>
                    </a>

                    <a class="footer-action-link" href="tel:+63288290480" aria-label="Call W68 at 8829-0480">
                        <span class="footer-action-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M7.3 3.5 10 7.8 8.2 9.7c1.1 2.2 2.9 4 5.1 5.1l1.9-1.8 4.3 2.7-.7 3.1c-.2.9-1 1.5-1.9 1.5C10 20.2 3.8 14 3.8 7.1c0-.9.6-1.7 1.5-1.9l2-.4Z"/></svg>
                        </span>
                        <span class="footer-action-copy"><b>8829-0480</b><small>Tap to call</small></span>
                    </a>
                </div>

                <a class="footer-action-link" href="tel:+639173239605" aria-label="Call W68 mobile 0917-3239-605">
                    <span class="footer-action-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M10 5h4M11 18.5h2"/></svg>
                    </span>
                    <span class="footer-action-copy"><b>0917-3239-605</b><small>Mobile • Tap to call</small></span>
                </a>

                <a class="footer-action-link" href="viber://chat?number=%2B639498818468" aria-label="Open Viber chat with W68 0949-8818-468">
                    <span class="footer-action-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 4.5h14v11H9l-4 4v-15Z"/><path d="M9 8.5c1.1 2.3 2.2 3.4 4.6 4.5"/></svg>
                    </span>
                    <span class="footer-action-copy"><b>0949-8818-468</b><small>Mobile/Viber • Open Viber</small></span>
                </a>
            </div>
        </div>
    </footer>

    <div class="cart-drawer" data-cart-drawer aria-hidden="true">
        <button class="cart-backdrop" type="button" data-cart-close aria-label="Close cart"></button>
        <aside class="cart-panel">
            <div class="cart-panel-head">
                <div><span>YOUR CART</span><strong>W68 Autoparts</strong></div>
                <button type="button" data-cart-close aria-label="Close">×</button>
            </div>
            <div class="cart-items" data-cart-items></div>
            <div class="cart-empty" data-cart-empty>Your cart is empty.</div>
            <div class="cart-summary">
                <span>Estimated total</span>
                <strong data-cart-total>₱0.00</strong>
                <small>Cart is saved in this browser for price-list convenience.</small>
            </div>
        </aside>
    </div>

    <div class="product-view-modal" data-product-modal hidden aria-hidden="true">
        <button class="product-view-backdrop" type="button" data-product-modal-close aria-label="Close product details"></button>
        <section class="product-view-card" role="dialog" aria-modal="true" aria-labelledby="product-view-title">
            <div class="product-view-image-wrap">
                <img data-product-modal-image src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="Product">
            </div>

            <div class="product-view-content">
                <span class="product-view-kicker">W68 PRODUCT DETAILS</span>
                <h2 id="product-view-title" data-product-modal-name>Product</h2>

                <div class="product-view-details">
                    <div>
                        <span>Product Code</span>
                        <strong data-product-modal-code>—</strong>
                    </div>
                    <div>
                        <span>Part Number</span>
                        <strong data-product-modal-part>—</strong>
                    </div>
                    <div>
                        <span>Brand</span>
                        <strong data-product-modal-brand>—</strong>
                    </div>
                    <div>
                        <span>Application</span>
                        <strong data-product-modal-application>—</strong>
                    </div>
                    <div>
                        <span>Position</span>
                        <strong data-product-modal-position>—</strong>
                    </div>
                    <div>
                        <span>Specification</span>
                        <strong data-product-modal-specification>—</strong>
                    </div>
                </div>

                <div class="product-view-price">
                    <span>Price</span>
                    <strong data-product-modal-price>₱0.00</strong>
                </div>

                <div class="product-view-actions">
                    <button type="button" class="product-view-back" data-product-modal-close>Back</button>
                    <button
                        type="button"
                        class="product-view-add add-cart"
                        data-product-modal-add
                        data-add-cart
                        data-id=""
                        data-name=""
                        data-price=""
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Add to Cart
                    </button>
                </div>
            </div>
        </section>
    </div>

    <div class="toast" data-toast>Added to cart</div>

    <script src="{{ asset('js/w68-storefront.js') }}?v=20260911-v12" defer></script>
</body>
</html>
