@php
    $logo = $logo ?? asset('images/sidebar_logo.png');

    $requestBasePath = str_replace('\\', '/', (string) request()->getBaseUrl());
    $requestBasePath = preg_replace('#/index\.php$#i', '', rtrim($requestBasePath, '/')) ?: '';
    $currentPublicBase = rtrim(request()->getSchemeAndHttpHost(), '/') . $requestBasePath;
    $currentProductImageBase = $currentProductImageBase ?? ($currentPublicBase . '/home/product-image/');

    $cardDataHelper = function ($product) use ($currentProductImageBase) {
        $price = (float) ($product->display_price ?? $product->selling_price ?? 0);
        $discountPercent = max(0, (float) ($product->discount_percent ?? 0));
        $discountedPrice = (float) ($product->discounted_price ?? $price);

        $hasDiscount = (bool) ($product->has_discount ?? false)
            || $discountPercent > 0.0001
            || ($price > 0 && $discountedPrice > 0 && $discountedPrice < $price);

        if (!$hasDiscount) {
            $discountPercent = 0;
            $discountedPrice = $price;
        }

        return [
            'id' => (string) $product->id,
            'image' => $currentProductImageBase
                . rawurlencode((string) $product->id)
                . '?v='
                . rawurlencode(
                    ($product->updated_at ?? null)
                        ? (string) strtotime((string) $product->updated_at)
                        : 'w68-20261024-nocache'
                ),
            'productCode' => (string) ($product->product_code ?? ''),
            'partNumber' => (string) ($product->part_number ?? ''),
            'description' => (string) ($product->description ?? ''),
            'application' => (string) ($product->application ?? ''),
            'specification' => (string) ($product->specification ?? ''),
            'position' => (string) ($product->position ?? ''),
            'brand' => (string) ($product->brand ?? ''),
            'price' => number_format($price, 2, '.', ''),
            'discountPercent' => number_format($discountPercent, 2, '.', ''),
            'discountedPrice' => number_format($discountedPrice, 2, '.', ''),
            'canAddToCart' => $price > 0 && $discountedPrice > 0,
            'hasDiscount' => $hasDiscount,
        ];
    };
@endphp

@foreach ($products as $product)
    @php $data = $cardDataHelper($product); @endphp

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
        data-product-discount-percent="{{ $data['discountPercent'] }}"
        data-product-discounted-price="{{ $data['discountedPrice'] }}"
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
                <span><b>Part No.</b> {{ $data['partNumber'] ?: '&mdash;' }}</span>
                <span><b>Brand</b> {{ $data['brand'] ?: '&mdash;' }}</span>
                <span><b>Application</b> {{ $data['application'] ?: '&mdash;' }}</span>
            </div>

            <div class="product-card-footer">
                <div class="product-price">
                    <small>PRICE</small>
                    <strong class="original-price{{ $data['hasDiscount'] ? ' is-discounted' : '' }}">{{ number_format((float) $data['price'], 2) }}</strong>
                    @if ($data['hasDiscount'])
                        <small class="discount-price-label">DISCOUNTED PRICE ({{ number_format((float) $data['discountPercent'], 2) }}%)</small>
                        <strong class="discount-price">{{ number_format((float) $data['discountedPrice'], 2) }}</strong>
                    @endif
                </div>

                <div class="product-card-actions">
                    @if ($data['canAddToCart'])
                        <button type="button" class="product-add-cart" data-add-cart>ADD TO CART</button>
                    @endif
                    <button type="button" class="product-view-cart" data-view-cart hidden>VIEW CART</button>
                </div>
            </div>
        </div>
    </article>
@endforeach
