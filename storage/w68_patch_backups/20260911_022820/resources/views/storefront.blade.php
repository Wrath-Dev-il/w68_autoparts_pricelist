<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="W68 Autoparts online price list and parts catalog.">
    <title>W68 Autoparts | Online Pricelist</title>
    <link rel="icon" href="{{ asset('build/assets/images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-storefront.css') }}?v=20260911-v4">
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
                <form action="{{ route('storefront') }}" method="GET" class="search-form">
                    @if ($brand !== '')<input type="hidden" name="brand" value="{{ $brand }}">@endif
                    @if ($description !== '')<input type="hidden" name="description" value="{{ $description }}">@endif
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Search product code, part number, description, application..."
                        autocomplete="off"
                    >
                    <button type="submit" aria-label="Search">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                        <span>Search</span>
                    </button>
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
            <div class="hero-main">
                <div class="hero-copy">
                    <span class="eyebrow">W68 AUTOPARTS ONLINE</span>
                    <h1>Find the right auto part, fast.</h1>
                    <p>Search the W68 catalog by product code, part number, description, application, or position.</p>
                    <div class="hero-actions">
                        <a class="primary-action" href="#products">Shop Parts</a>
                        <a class="secondary-action" href="#brands">Browse Brands</a>
                    </div>
                </div>

                @if (count($newItems))
                    <div class="hero-new-carousel" id="hero-new-items" data-slide-carousel data-interval="2000">
                        <div class="hero-new-title">
                            <span>NEW THIS YEAR</span>
                            <strong>Newly Added Items {{ date('Y') }}</strong>
                        </div>
                        <button class="hero-slide-arrow previous" type="button" data-slide-prev aria-label="Previous new item">‹</button>
                        <div class="hero-new-slides">
                            @foreach ($newItems as $index => $product)
                                <article class="hero-new-slide {{ $index === 0 ? 'active' : '' }}" data-slide>
                                    <img src="{{ $product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $product->name }}" loading="lazy" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                                    <div class="hero-new-info">
                                        <span>{{ $product->display_category }}</span>
                                        <strong title="{{ $product->name }}">{{ $product->name }}</strong>
                                        <small>{{ $product->product_code }}@if ($product->part_number) • {{ $product->part_number }}@endif</small>
                                    </div>
                                    <div class="hero-new-action">
                                        <b>₱{{ number_format((float) $product->display_price, 2) }}</b>
                                        <button type="button" class="add-cart hero-add-cart" data-add-cart data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->display_price }}">Add to Cart</button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <button class="hero-slide-arrow next" type="button" data-slide-next aria-label="Next new item">›</button>
                    </div>
                @endif

                <div class="hero-mark" aria-hidden="true">
                    <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="">
                </div>
            </div>

            <div class="hero-side">
                <article class="promo-card top-seller-card" data-slide-carousel data-interval="2000">
                    <span>MOST SOLD BY CATEGORY</span>
                    @if (count($topCategorySellers))
                        <div class="top-seller-slides">
                            @foreach ($topCategorySellers as $index => $seller)
                                <div class="top-seller-slide {{ $index === 0 ? 'active' : '' }}" data-slide>
                                    <small class="top-seller-category">{{ $seller->category }}</small>
                                    <div class="top-seller-product">
                                        <img src="{{ $seller->product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $seller->product->name }}" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                                        <div>
                                            <strong>{{ $seller->product->name }}</strong>
                                            <small>{{ $seller->product->product_code }}</small>
                                            @if ($seller->product->part_number)<small>Part No. {{ $seller->product->part_number }}</small>@endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if (count($topCategorySellers) > 1)
                            <div class="top-seller-nav">
                                <button type="button" data-slide-prev aria-label="Previous top seller">‹</button>
                                <span>Top 1 product in each category</span>
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
                        <div class="fast-lookup-product">
                            <img src="{{ $fastLookup->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $fastLookup->name }}" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                            <div>
                                <strong>{{ $fastLookup->name }}</strong>
                                <small>{{ $fastLookup->product_code }}</small>
                                @if ($fastLookup->part_number)<small>Part No. {{ $fastLookup->part_number }}</small>@endif
                                @if ($fastLookup->application)<small>{{ $fastLookup->application }}</small>@endif
                                @if ($fastLookup->display_description)<small>{{ $fastLookup->display_description }}</small>@endif
                            </div>
                        </div>
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
                        <h2>{{ $search !== '' || $brand !== '' || $description !== '' || $applicationSearch !== '' || $partNumberSearch !== '' || $positionSearch !== '' ? 'Filtered Products' : 'Recommended for You' }}</h2>
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

                    <button type="submit" class="toolbar-search">Search</button>
                    <a class="toolbar-clear" href="{{ route('storefront') }}#products">Clear</a>
                </form>

                @if (count($products))
                    <div class="product-grid">
                        @foreach ($products as $product)
                            @php $price = (float) $product->display_price; @endphp
                            <article class="product-card">
                                <div class="product-image-wrap">
                                    <img src="{{ $product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $product->name }}" loading="lazy" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                                    <span class="w68-badge">W68</span>
                                </div>
                                <div class="product-info">
                                    <div class="product-code">{{ $product->product_code }}</div>
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
        <div class="shell footer-grid">
            <div class="footer-brand">
                <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="W68 Autoparts">
                <div><strong>W68 AUTOPARTS</strong><span>Online Pricelist & Parts Catalog</span></div>
            </div>
            <div>
                <strong>Catalog</strong>
                <a href="#products">All Products</a>
                <a href="#brands">Brands</a>
            </div>
            <div>
                <strong>Find Parts</strong>
                <a href="#descriptions">Descriptions</a>
                <a href="#hero-new-items">New This Year</a>
            </div>
            <div>
                <strong>W68 Autoparts</strong>
                <span>Catalog powered by the W68 product masterlist.</span>
            </div>
        </div>
        <div class="shell copyright">© {{ date('Y') }} W68 Autoparts. All rights reserved.</div>
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

    <div class="toast" data-toast>Added to cart</div>

    <script src="{{ asset('js/w68-storefront.js') }}?v=20260911-v4" defer></script>
</body>
</html>
