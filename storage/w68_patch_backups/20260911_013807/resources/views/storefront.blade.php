<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="W68 Autoparts online price list and parts catalog.">
    <title>W68 Autoparts | Online Pricelist</title>
    <link rel="icon" href="{{ asset('build/assets/images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-storefront.css') }}">
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
                    <a href="#categories">Categories</a>
                    <a href="#products">Products</a>
                    <a href="#footer">Help</a>
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
                    @if ($category !== '')
                        <input type="hidden" name="category" value="{{ $category }}">
                    @endif
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
                    <p>Search real products from the W68 masterlist by product code, part number, description, or vehicle application.</p>
                    <div class="hero-actions">
                        <a class="primary-action" href="#products">Shop Parts</a>
                        <a class="secondary-action" href="#categories">Browse Categories</a>
                    </div>
                </div>
                <div class="hero-mark" aria-hidden="true">
                    <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="">
                </div>
            </div>

            <div class="hero-side">
                <article class="promo-card promo-yellow">
                    <span>W68 PRICE LIST</span>
                    <strong>Real product data</strong>
                    <small>Powered by core4_masterlist</small>
                </article>
                <article class="promo-card promo-light">
                    <span>FAST LOOKUP</span>
                    <strong>Search by part no.</strong>
                    <small>Product code • application • description</small>
                </article>
            </div>
        </section>

        <section class="shell service-strip" aria-label="Store highlights">
            <div><strong>{{ number_format($stats['products']) }}</strong><span>Online Products</span></div>
            <div><strong>{{ number_format($stats['in_stock']) }}</strong><span>Products In Stock</span></div>
            <div><strong>{{ number_format($stats['categories']) }}</strong><span>Categories</span></div>
            <div><strong>W68</strong><span>Trusted Autoparts Catalog</span></div>
        </section>

        @if ($dbError)
            <section class="shell database-alert">
                <strong>Database connection needed.</strong>
                <span>{{ $dbError }} Default database: <code>core4_masterlist</code>.</span>
            </section>
        @endif

        <section class="shell marketplace-section" id="categories">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">DISCOVER</span>
                    <h2>Shop by Category</h2>
                </div>
                @if ($category !== '')
                    <a class="view-all" href="{{ route('storefront', array_filter(['q' => $search])) }}">Clear category</a>
                @endif
            </div>

            <div class="category-grid">
                <a class="category-card {{ $category === '' ? 'active' : '' }}" href="{{ route('storefront', array_filter(['q' => $search])) }}">
                    <span class="category-icon">ALL</span>
                    <strong>All Products</strong>
                    <small>{{ number_format($stats['products']) }} items</small>
                </a>
                @foreach ($categories as $item)
                    <a class="category-card {{ $category === $item->name ? 'active' : '' }}" href="{{ route('storefront', array_filter(['q' => $search, 'category' => $item->name])) }}">
                        <span class="category-icon">{{ strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $item->name), 0, 2)) ?: 'W8' }}</span>
                        <strong>{{ $item->name }}</strong>
                        <small>{{ number_format($item->total) }} items</small>
                    </a>
                @endforeach
            </div>
        </section>

        @if (count($featured))
            <section class="shell marketplace-section featured-section">
                <div class="section-heading compact-heading">
                    <div>
                        <span class="section-kicker hot">W68 PICKS</span>
                        <h2>Fresh from the Catalog</h2>
                    </div>
                    <a class="view-all" href="#products">See all products →</a>
                </div>
                <div class="featured-row">
                    @foreach ($featured as $product)
                        <article class="featured-card">
                            <div class="featured-image">
                                <img src="{{ $product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $product->name }}" loading="lazy" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                            </div>
                            <div class="featured-content">
                                <span>{{ $product->product_code }}</span>
                                <strong>{{ $product->name }}</strong>
                                <div class="featured-bottom">
                                    <b>₱{{ number_format((float) $product->display_price, 2) }}</b>
                                    <button type="button" class="mini-add" data-add-cart data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->display_price }}">+</button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="product-zone" id="products">
            <div class="shell marketplace-section products-section">
                <div class="section-heading product-heading">
                    <div>
                        <span class="section-kicker">W68 CATALOG</span>
                        <h2>{{ $search !== '' ? 'Search Results' : ($category !== '' ? $category : 'Recommended for You') }}</h2>
                        @if ($search !== '')
                            <p>Showing matches for “{{ $search }}”</p>
                        @endif
                    </div>

                    <form class="sort-form" action="{{ route('storefront') }}" method="GET">
                        @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                        @if ($category !== '')<input type="hidden" name="category" value="{{ $category }}">@endif
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort" onchange="this.form.submit()">
                            <option value="latest" @selected($sort === 'latest')>Latest</option>
                            <option value="price_low" @selected($sort === 'price_low')>Price: Low to High</option>
                            <option value="price_high" @selected($sort === 'price_high')>Price: High to Low</option>
                            <option value="stock" @selected($sort === 'stock')>Stock</option>
                        </select>
                    </form>
                </div>

                @if (count($products))
                    <div class="product-grid">
                        @foreach ($products as $product)
                            @php
                                $stock = is_null($product->on_hand) ? null : (int) $product->on_hand;
                                $price = (float) $product->display_price;
                            @endphp
                            <article class="product-card">
                                <div class="product-image-wrap">
                                    <img src="{{ $product->image_url ?: asset('build/assets/images/sidebar_logo.png') }}" alt="{{ $product->name }}" loading="lazy" onerror="this.src='{{ asset('build/assets/images/sidebar_logo.png') }}'">
                                    <span class="w68-badge">W68</span>
                                    @if (!is_null($stock))
                                        <span class="stock-badge {{ $stock > 0 ? 'in-stock' : 'out-stock' }}">{{ $stock > 0 ? $stock . ' in stock' : 'Out of stock' }}</span>
                                    @endif
                                </div>
                                <div class="product-info">
                                    <div class="product-code">{{ $product->product_code }}</div>
                                    <h3 title="{{ $product->name }}">{{ $product->name }}</h3>
                                    @if ($product->part_number)
                                        <div class="part-number">Part No: {{ $product->part_number }}</div>
                                    @endif
                                    <div class="product-meta">
                                        <span>{{ $product->display_category }}</span>
                                        @if ($product->application)<span>{{ $product->application }}</span>@endif
                                    </div>
                                    <div class="product-card-bottom">
                                        <div class="price-wrap">
                                            <span class="currency">₱</span><strong>{{ number_format($price, 2) }}</strong>
                                        </div>
                                        <button
                                            type="button"
                                            class="add-cart"
                                            data-add-cart
                                            data-id="{{ $product->id }}"
                                            data-name="{{ $product->name }}"
                                            data-price="{{ $price }}"
                                            {{ !is_null($stock) && $stock <= 0 ? 'disabled' : '' }}
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                            Add
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
                        <p>Try another product code, part number, description, or vehicle application.</p>
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
                <a href="#categories">Categories</a>
            </div>
            <div>
                <strong>Search</strong>
                <a href="{{ route('storefront', ['q' => 'Toyota']) }}">Toyota Parts</a>
                <a href="{{ route('storefront', ['q' => 'Isuzu']) }}">Isuzu Parts</a>
            </div>
            <div>
                <strong>W68 Autoparts</strong>
                <span>Product prices and inventory are loaded from core4_masterlist.</span>
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

    <script src="{{ asset('js/w68-storefront.js') }}" defer></script>
</body>
</html>
