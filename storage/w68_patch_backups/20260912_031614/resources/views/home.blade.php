<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="W68 Autoparts customer online pricelist.">
    <title>W68 Autoparts | Home</title>
    <link rel="icon" href="{{ asset('build/assets/images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-home.css') }}?v=20260912-v32">
    <script src="{{ asset('js/w68-home.js') }}?v=20260912-v32" defer></script>
</head>
<body>
    @php
        $logo = asset('build/assets/images/sidebar_logo.png');

        $productData = function ($product) use ($logo) {
            return [
                'id' => (string) $product->id,
                'name' => (string) ($product->name ?: $product->display_description),
                'image' => (string) ($product->image_url ?: $logo),
                'productCode' => (string) ($product->display_product_code ?? ''),
                'partNumber' => (string) ($product->part_number ?? ''),
                'description' => (string) ($product->display_description ?? ''),
                'application' => (string) ($product->application ?? ''),
                'specification' => (string) ($product->specification ?? ''),
                'position' => (string) ($product->position ?? ''),
                'brand' => (string) ($product->display_brand ?? ''),
                'price' => number_format((float) ($product->display_price ?? 0), 2, '.', ''),
            ];
        };
    @endphp

    <header class="customer-nav" data-customer-nav>
        <div class="customer-nav-shell">
            <button
                type="button"
                class="nav-expand-button"
                data-nav-expand
                aria-label="Expand navigation"
                aria-expanded="true"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 9 5 5 5-5"/>
                </svg>
            </button>

            <div class="nav-main-content" data-nav-main>
                <a class="nav-brand" href="{{ route('home') }}">
                    <img src="{{ $logo }}" alt="W68 Autoparts">
                    <span>
                        <strong>W68 AUTOPARTS</strong>
                        <small>Online Pricelist</small>
                    </span>
                </a>

                <a class="nav-new-items" href="#new-items">NEW ITEMS</a>

                <form
                    class="nav-filter-form"
                    action="{{ route('home') }}#products"
                    method="GET"
                    data-nav-filter-form
                >
                    <label>
                        <span>Brand</span>
                        <select name="brand" data-auto-submit>
                            <option value="">All Brands</option>
                            @foreach ($filterOptions['brands'] as $option)
                                <option value="{{ $option }}" @selected($filters['brand'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Description</span>
                        <select name="description" data-auto-submit>
                            <option value="">All Descriptions</option>
                            @foreach ($filterOptions['descriptions'] as $option)
                                <option value="{{ $option }}" @selected($filters['description'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Position</span>
                        <select name="position" data-auto-submit>
                            <option value="">All Positions</option>
                            @foreach ($filterOptions['positions'] as $option)
                                <option value="{{ $option }}" @selected($filters['position'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Application</span>
                        <select name="application" data-auto-submit>
                            <option value="">All Applications</option>
                            @foreach ($filterOptions['applications'] as $option)
                                <option value="{{ $option }}" @selected($filters['application'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>

                <form action="{{ route('logout') }}" method="POST" class="nav-logout-form">
                    @csrf
                    <button type="submit" class="nav-logout">LOGOUT</button>
                </form>
            </div>

            <button class="nav-cart-button" type="button" data-cart-open aria-label="Open cart">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H6"/>
                    <circle cx="10" cy="20" r="1.4"/>
                    <circle cx="18" cy="20" r="1.4"/>
                </svg>
                <span>ADD TO CART</span>
                <b data-cart-count>0</b>
            </button>
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="page-status shell">{{ session('status') }}</div>
        @endif

        <section class="new-items-section shell" id="new-items">
            <div class="section-heading">
                <div>
                    <span>JUST ADDED</span>
                    <h1>New Items</h1>
                </div>
                <p>Latest W68 products. Prices shown here use <strong>selling_price</strong>.</p>
            </div>

            @if (count($newItems))
                <div class="new-items-track">
                    @foreach ($newItems as $product)
                        @php
                            $data = $productData($product);
                        @endphp

                        <article
                            class="new-item-card"
                            tabindex="0"
                            role="button"
                            data-product-card
                            @foreach ($data as $key => $value)
                                data-product-{{ \Illuminate\Support\Str::kebab($key) }}="{{ $value }}"
                            @endforeach
                        >
                            <img
                                src="{{ $data['image'] }}"
                                alt="{{ $data['description'] ?: $data['name'] }}"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='{{ $logo }}'"
                            >

                            <div>
                                <small>{{ $data['displayProductCode'] ?? $data['productCode'] }}</small>
                                <strong>{{ $data['description'] ?: $data['name'] }}</strong>
                                <span>{{ $data['brand'] ?: 'W68 Autoparts' }}</span>
                                <b>₱{{ number_format((float) $data['price'], 2) }}</b>
                            </div>

                            <button
                                type="button"
                                class="mini-add-cart"
                                data-add-cart
                                aria-label="Add {{ $data['name'] }} to cart"
                            >
                                +
                            </button>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="empty-state">No new items are available right now.</div>
            @endif
        </section>

        <section class="search-section" id="search">
            <div class="shell">
                <div class="section-heading">
                    <div>
                        <span>FIND YOUR PART</span>
                        <h2>Search Products</h2>
                    </div>
                </div>

                <form class="product-search-form" action="{{ route('home') }}#products" method="GET">
                    <label>
                        <span>Product Code</span>
                        <input
                            type="search"
                            name="product_code"
                            value="{{ $filters['product_code'] }}"
                            placeholder="Search product code"
                        >
                    </label>

                    <label>
                        <span>Part Number</span>
                        <input
                            type="search"
                            name="part_number"
                            value="{{ $filters['part_number'] }}"
                            placeholder="Search part number"
                        >
                    </label>

                    <label>
                        <span>Description</span>
                        <input
                            type="search"
                            name="description"
                            value="{{ $filters['description'] }}"
                            placeholder="Search description"
                        >
                    </label>

                    <label>
                        <span>Brand</span>
                        <input
                            type="search"
                            name="brand"
                            value="{{ $filters['brand'] }}"
                            placeholder="Search brand"
                        >
                    </label>

                    <label>
                        <span>Application</span>
                        <input
                            type="search"
                            name="application"
                            value="{{ $filters['application'] }}"
                            placeholder="Search application"
                        >
                    </label>

                    @if ($filters['position'] !== '')
                        <input type="hidden" name="position" value="{{ $filters['position'] }}">
                    @endif

                    <div class="search-actions">
                        <button type="submit" class="search-button">SEARCH</button>
                        <a href="{{ route('home') }}#products" class="clear-button">CLEAR</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="products-section shell" id="products">
            <div class="section-heading">
                <div>
                    <span>W68 CATALOG</span>
                    <h2>Products</h2>
                </div>

                @if (method_exists($products, 'total'))
                    <p>{{ number_format($products->total()) }} matching products</p>
                @endif
            </div>

            @if (count($products))
                <div class="product-grid">
                    @foreach ($products as $product)
                        @php
                            $data = $productData($product);
                        @endphp

                        <article
                            class="product-card"
                            id="product-{{ $product->id }}"
                            tabindex="0"
                            role="button"
                            aria-label="View {{ $data['description'] ?: $data['name'] }}"
                            data-product-card
                            @foreach ($data as $key => $value)
                                data-product-{{ \Illuminate\Support\Str::kebab($key) }}="{{ $value }}"
                            @endforeach
                        >
                            <div class="product-image-wrap">
                                <img
                                    src="{{ $data['image'] }}"
                                    alt="{{ $data['description'] ?: $data['name'] }}"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='{{ $logo }}'"
                                >
                                <span class="product-badge">W68</span>
                            </div>

                            <div class="product-card-body">
                                <div class="product-code">{{ $data['productCode'] ?: 'NO PRODUCT CODE' }}</div>

                                <h3>{{ $data['description'] ?: $data['name'] }}</h3>

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

                                    <button
                                        type="button"
                                        class="product-add-cart"
                                        data-add-cart
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if (method_exists($products, 'links'))
                    <div class="home-pagination">
                        {{ $products->onEachSide(1)->links() }}
                    </div>
                @endif
            @else
                <div class="empty-state">No products matched your filters.</div>
            @endif
        </section>
    </main>

    {{-- Cart drawer --}}
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

            <div class="cart-items" data-cart-items></div>
            <div class="cart-empty" data-cart-empty>Your cart is empty.</div>

            <footer class="cart-footer">
                <span>Cart Total</span>
                <strong data-cart-total>₱0.00</strong>
            </footer>
        </section>
    </aside>

    {{-- Product view modal --}}
    <div class="product-modal" data-product-modal hidden aria-hidden="true">
        <button class="modal-backdrop" type="button" data-product-modal-close aria-label="Back"></button>

        <section class="product-modal-card" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
            <div class="modal-product-image">
                <img data-product-modal-image src="{{ $logo }}" alt="Product">
            </div>

            <div class="modal-product-content">
                <span>W68 PRODUCT</span>
                <h2 id="product-modal-title" data-product-modal-name>Product</h2>

                <div class="modal-detail-grid">
                    <div><span>Product Code</span><strong data-product-modal-code>—</strong></div>
                    <div><span>Part Number</span><strong data-product-modal-part>—</strong></div>
                    <div><span>Application</span><strong data-product-modal-application>—</strong></div>
                    <div><span>Specification</span><strong data-product-modal-specification>—</strong></div>
                    <div><span>Position</span><strong data-product-modal-position>—</strong></div>
                    <div><span>Brand</span><strong data-product-modal-brand>—</strong></div>
                </div>

                <div class="modal-actions">
                    <span>ACTION</span>

                    <div>
                        <button type="button" class="modal-add-cart" data-product-modal-add>
                            ADD TO CART
                        </button>
                        <button type="button" class="modal-back" data-product-modal-close>
                            BACK
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Cart item details modal --}}
    <div class="cart-item-modal" data-cart-item-modal hidden aria-hidden="true">
        <button class="modal-backdrop" type="button" data-cart-item-modal-close aria-label="Close"></button>

        <section class="cart-item-modal-card" role="dialog" aria-modal="true">
            <div class="cart-item-modal-image">
                <img data-cart-item-modal-image src="{{ $logo }}" alt="Product">
            </div>

            <div class="cart-item-modal-content">
                <span>ADD TO CART</span>
                <h2 data-cart-item-modal-name>Product</h2>

                <div class="modal-detail-grid">
                    <div><span>Product Code</span><strong data-cart-item-modal-code>—</strong></div>
                    <div><span>Part Number</span><strong data-cart-item-modal-part>—</strong></div>
                    <div><span>Application</span><strong data-cart-item-modal-application>—</strong></div>
                    <div><span>Specification</span><strong data-cart-item-modal-specification>—</strong></div>
                    <div><span>Position</span><strong data-cart-item-modal-position>—</strong></div>
                    <div><span>Brand</span><strong data-cart-item-modal-brand>—</strong></div>
                </div>

                <div class="cart-order-summary">
                    <div><span>Price</span><strong data-cart-item-modal-price>₱0.00</strong></div>
                    <div><span>Ordered Qty</span><strong data-cart-item-modal-qty>0</strong></div>
                    <div><span>Total</span><strong data-cart-item-modal-total>₱0.00</strong></div>
                </div>

                <div class="modal-actions">
                    <span>ACTION</span>
                    <div>
                        <button type="button" class="modal-back" data-cart-item-modal-close>CLOSE</button>
                    </div>
                </div>
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
</body>
</html>
