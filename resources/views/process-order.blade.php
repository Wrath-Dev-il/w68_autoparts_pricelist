@php
    $viewMode = (bool) ($viewMode ?? false);
    $returnViewMode = (bool) ($returnViewMode ?? false);
    $viewOrder = $viewOrder ?? null;
    $orderCode = $viewMode ? (string) ($viewOrder['order_code'] ?? 'Order') : '';
    $backToOrdersUrl = route('orders') . ($returnViewMode ? '#returns' : '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>W68 Autoparts | {{ $returnViewMode ? 'View Return' : ($viewMode ? 'View Order' : 'Process Order') }}</title>
    <link rel="icon" href="{{ asset('build/assets/images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-process-order.css') }}?v=20260915-v98">
    <script src="{{ asset('js/w68-process-order.js') }}?v=20260915-v98" defer></script>
</head>
<body
    data-process-url="{{ $viewMode ? '' : route('home.orders.process') }}"
    data-orders-url="{{ ltrim(route('orders', [], false), '/') }}"
    data-view-mode="{{ $viewMode ? '1' : '0' }}"
>
    <main class="process-page-shell {{ $viewMode ? 'is-view-mode' : '' }}">
        <header class="process-page-header">
            <a class="process-brand" href="{{ $viewMode ? $backToOrdersUrl : route('home') }}" aria-label="{{ $viewMode ? 'Back to W68 Orders' : 'Back to W68 Home' }}">
                <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="W68 Autoparts & Service Center">
                <div>
                    <span>W68 AUTOPARTS &amp; SERVICE CENTER</span>
                    <strong>{{ $returnViewMode ? 'VIEW RETURN' : ($viewMode ? 'VIEW ORDER' : 'PROCESS ORDER') }}</strong>
                </div>
            </a>
            <a class="process-header-home" href="{{ $viewMode ? $backToOrdersUrl : route('home') }}">{{ $viewMode ? 'ORDERS' : 'HOME' }}</a>
        </header>

        <section class="process-order-panel">
            <div class="process-heading">
                <div>
                    <span class="process-kicker">{{ $returnViewMode ? 'RETURN DETAILS' : ($viewMode ? 'ORDER DETAILS' : 'ORDER REVIEW') }}</span>
                    <h1>{{ $viewMode ? ($orderCode ?: ($returnViewMode ? 'View Return' : 'View Order')) : 'Process Order' }}</h1>
                    <p>
                        @if ($returnViewMode)
                            This view contains only the item(s) returned from this invoice.
                        @elseif ($viewMode)
                            Review the items and print preview for this order.
                        @else
                            Review the selected cart items before creating your order.
                        @endif
                    </p>
                    @if ($viewMode && $viewOrder)
                        <div class="process-view-meta">
                            @if ($returnViewMode)
                                <span>Order: <strong>{{ $viewOrder['portal_order_code'] ?: '—' }}</strong></span>
                                <span>Invoice: <strong>{{ $viewOrder['invoice_no'] ?: '—' }}</strong></span>
                            @else
                                <span>Sales Note: <strong>{{ $viewOrder['sales_number'] ?: '—' }}</strong></span>
                            @endif
                            <span>Date: <strong>{{ $viewOrder['date'] ?: '—' }}</strong></span>
                            <span>Status: <strong>{{ $viewOrder['status_label'] ?: 'PROCESSED' }}</strong></span>
                        </div>
                    @endif
                </div>
                <div class="process-heading-count">
                    <span>{{ $returnViewMode ? 'RETURNED ITEMS' : ($viewMode ? 'ORDER ITEMS' : 'SELECTED ITEMS') }}</span>
                    <strong>{{ $items->count() }}</strong>
                </div>
            </div>

            @if ($items->isEmpty())
                <div class="process-empty-state">
                    <strong>{{ $returnViewMode ? 'This return has no items.' : ($viewMode ? 'This order has no items.' : 'No selected cart items.') }}</strong>
                    <span>{{ $returnViewMode ? 'Return to Orders and choose another return.' : ($viewMode ? 'Return to Orders and choose another order.' : 'Go back to Home, select at least one item in your cart, then choose PROCESS ORDER.') }}</span>
                </div>
            @else
                <div class="process-items-scroll" aria-label="{{ $returnViewMode ? 'Returned items' : ($viewMode ? 'Order items' : 'Selected order items') }}">
                    @foreach ($items as $item)
                        <article
                            class="process-item-card {{ $viewMode ? 'is-read-only' : '' }}"
                            data-process-item
                            data-product-id="{{ (int) $item['id'] }}"
                            data-unit-price="{{ number_format($item['hasDiscount'] ? $item['discountedPrice'] : $item['price'], 2, '.', '') }}"
                            @unless($viewMode)
                                data-update-url="{{ route('home.cart.update', ['product' => (int) $item['id']]) }}"
                            @endunless
                        >
                            <div class="process-description-row">
                                <span>DESCRIPTION</span>
                                <strong>{{ $item['description'] ?: 'W68 Product' }}</strong>
                            </div>

                            <div class="process-item-body">
                                <div class="process-item-image">
                                    <img
                                        src="{{ $item['image'] }}"
                                        alt="{{ $item['description'] ?: $item['productCode'] }}"
                                        loading="lazy"
                                        onerror="this.onerror=null;this.src='{{ asset('build/assets/images/sidebar_logo.png') }}';"
                                    >
                                </div>

                                <div class="process-item-details">
                                    @unless($viewMode)
                                        <button
                                            type="button"
                                            class="process-remove-item"
                                            data-remove-selected
                                            aria-label="Remove {{ $item['description'] ?: $item['productCode'] }} from selected items"
                                            title="Remove from selected items"
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-2 6h10l-1 11H8L7 9Zm3 2v7h2v-7h-2Zm4 0v7h2v-7h-2Z"/>
                                            </svg>
                                        </button>
                                    @endunless

                                    <div class="process-meta-stack">
                                        <div><span>PRODUCT CODE</span><strong>{{ $item['productCode'] ?: '—' }}</strong></div>
                                        <div><span>PART NUMBER</span><strong>{{ $item['partNumber'] ?: '—' }}</strong></div>
                                        <div><span>APPLICATION</span><strong>{{ $item['application'] ?: '—' }}</strong></div>
                                    </div>

                                    <div class="process-item-bottom-row">
                                        <div class="process-brand-cell">
                                            <span>BRAND</span>
                                            <strong>{{ $item['brand'] ?: '—' }}</strong>
                                        </div>

                                        <div class="process-order-math">
                                            <div class="process-math-cell">
                                                <span>PRICE</span>
                                                <strong class="process-effective-price">
                                                    {{ number_format($item['hasDiscount'] ? $item['discountedPrice'] : $item['price'], 2) }}
                                                </strong>
                                            </div>
                                            <div class="process-math-cell process-math-qty">
                                                <span>QTY</span>
                                                <strong data-qty-value>{{ $item['qty'] }}</strong>
                                            </div>
                                            <div class="process-math-cell">
                                                <span>TOTAL</span>
                                                <strong class="process-line-total" data-line-total>{{ number_format($item['lineTotal'], 2) }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                @unless($viewMode)
                    <div class="process-empty-state" data-live-empty-state hidden>
                        <strong>No selected cart items.</strong>
                        <span>Go back to Home, select at least one item in your cart, then choose PROCESS ORDER.</span>
                    </div>
                @endunless
            @endif

            <footer class="process-order-footer">
                <div class="process-grand-summary">
                    <div>
                        <span>TOTAL ITEMS</span>
                        <strong data-total-items>{{ number_format($totalItems) }}</strong>
                    </div>
                    <div>
                        <span>TOTAL PRICE</span>
                        <strong data-total-price>{{ number_format($totalPrice, 2) }}</strong>
                    </div>
                </div>

                <div class="process-error" data-process-error hidden></div>

                <div class="process-actions {{ $viewMode ? 'process-view-actions' : '' }}">
                    @if ($viewMode)
                        <a class="process-back-button" href="{{ $backToOrdersUrl }}">BACK TO ORDERS</a>
                        <button type="button" class="process-final-button process-preview-button" data-view-print-preview @disabled($items->isEmpty())>
                            VIEW PRINT PREVIEW
                        </button>
                    @else
                        <a class="process-back-button" href="{{ route('home') }}">GO BACK TO HOME</a>
                        <button type="button" class="process-final-button" data-final-process @disabled($items->isEmpty())>
                            PROCESS ORDER
                        </button>
                    @endif
                </div>
            </footer>
        </section>
    </main>

    <div class="order-confirm-modal {{ $viewMode ? 'is-print-only' : '' }}" data-order-confirm-modal hidden aria-hidden="true">
        <button type="button" class="order-modal-backdrop" data-order-confirm-close aria-label="Close print preview"></button>
        <section class="order-confirm-card" role="dialog" aria-modal="true" aria-labelledby="order-confirm-title">
            <header class="order-confirm-header">
                <div>
                    <span class="order-confirm-kicker">PRINT PREVIEW</span>
                    <h2 id="order-confirm-title">{{ $viewMode ? ($orderCode ?: ($returnViewMode ? 'Return' : 'Order')) : 'Order Confirmation' }}</h2>
                </div>
                <button type="button" class="order-confirm-x" data-order-confirm-close aria-label="Close">×</button>
            </header>

            <div class="order-print-preview">
                <div class="order-print-head">
                    <strong>W68 Autoparts &amp; Service Center</strong>
                    <span>48 Timothy ST. Multinational Village Parañaque City</span>
                    <span>Tel No. 8553-9092 / 8829-0480 &nbsp; MOBILE: 0917-3239-605 &nbsp; VIBER: 0949-8818-468</span>
                </div>

                <div class="order-print-table-wrap">
                    <table class="order-print-table">
                        <thead>
                            <tr>
                                <th>QTY</th>
                                <th>PRODUCT CODE</th>
                                <th>PART NUMBER</th>
                                <th>DESCRIPTION</th>
                                <th>UNIT PRICE</th>
                                <th>DISCOUNT</th>
                                <th>TOTAL UNIT PRICE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>{{ number_format((int) $item['qty']) }}</td>
                                    <td>{{ $item['productCode'] ?: '—' }}</td>
                                    <td>{{ $item['partNumber'] ?: '—' }}</td>
                                    <td>
                                        <strong>{{ $item['description'] ?: 'W68 Product' }}</strong>
                                        @if (!empty($item['application']))
                                            <span>{{ $item['application'] }}</span>
                                        @endif
                                        @if (!empty($item['brand']))
                                            <span>{{ $item['brand'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($item['price'], 2) }}</td>
                                    <td>{{ number_format($item['discountPercent'], 2) }}%</td>
                                    <td>{{ number_format($item['lineTotal'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="order-invoice-amount">
                    <span>INVOICE AMOUNT:</span>
                    <strong>{{ number_format($totalPrice, 2) }}</strong>
                </div>
            </div>

            @if ($viewMode)
                <footer class="order-confirm-actions print-only-actions">
                    <button type="button" class="order-confirm-cancel print-preview-close" data-order-confirm-close>CLOSE</button>
                </footer>
            @else
                <div class="order-terms-zone" data-terms-zone>
                    <label class="order-terms-check">
                        <input type="checkbox" data-terms-checkbox>
                        <span class="order-terms-box" aria-hidden="true"></span>
                        <span>TERMS AND AGREEMENT</span>
                    </label>
                    <small>Tap to read and agree before processing your order.</small>
                </div>

                <div class="order-confirm-error" data-confirm-error hidden></div>

                <footer class="order-confirm-actions">
                    <button type="button" class="order-confirm-cancel" data-order-confirm-close>CANCEL</button>
                    <button type="button" class="order-confirm-process" data-confirm-process disabled>PROCESS ORDER</button>
                </footer>
            @endif
        </section>
    </div>

    @unless($viewMode)
        <div class="terms-agreement-modal" data-terms-modal hidden aria-hidden="true">
            <button type="button" class="order-modal-backdrop terms-backdrop" data-terms-cancel aria-label="Cancel terms and agreement"></button>
            <section class="terms-agreement-card" role="dialog" aria-modal="true" aria-labelledby="terms-title">
                <div class="terms-agreement-icon">!</div>
                <span class="terms-agreement-kicker">TERMS AND AGREEMENT</span>
                <h2 id="terms-title">Please review before agreeing</h2>
                <div class="terms-agreement-copy">
                    <p>I confirm that I reviewed the selected products, quantities, prices, discounts, and total amount shown in the Print Preview.</p>
                    <p>I understand that selecting <strong>PROCESS ORDER</strong> will register this order in W68 and create an <strong>Open Sales Note</strong> for processing.</p>
                    <p>I confirm that the order details shown are correct before I continue.</p>
                </div>
                <div class="terms-agreement-actions">
                    <button type="button" class="terms-cancel" data-terms-cancel>CANCEL</button>
                    <button type="button" class="terms-agree" data-terms-agree>AGREE</button>
                </div>
            </section>
        </div>

        {{-- Same W68 tracing loader used on Home, shown while the Sales Note/order is being created. --}}
        <div class="process-loading" data-process-loading hidden aria-hidden="true">
            <svg class="w68-process-loader-svg" viewBox="0 0 750 260" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="W68 loading">
                <defs>
                    <linearGradient id="processLoaderMaroonGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#a31c3f" />
                        <stop offset="40%" stop-color="#800020" />
                        <stop offset="100%" stop-color="#4a0011" />
                    </linearGradient>
                    <linearGradient id="processLoaderGoldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#FFF5C0" />
                        <stop offset="30%" stop-color="#FFD700" />
                        <stop offset="70%" stop-color="#D4AF37" />
                        <stop offset="100%" stop-color="#996515" />
                    </linearGradient>
                </defs>
                <g class="w68-process-loader-trace" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="#800020" stroke-width="16" fill="none" />
                    <path d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="#d4af37" stroke-width="16" fill="none" />
                    <path d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="#d4af37" stroke-width="16" fill="none" />
                </g>
                <g stroke-linecap="round" stroke-linejoin="round">
                    <path data-w68-process-loader-path data-loader-color="#800020" class="w68-process-loader-maroon" d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="url(#processLoaderMaroonGrad)" stroke-width="16" fill="none" />
                    <path data-w68-process-loader-path data-loader-color="#FFD700" class="w68-process-loader-gold" d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="url(#processLoaderGoldGrad)" stroke-width="16" fill="none" />
                    <path data-w68-process-loader-path data-loader-color="#FFD700" class="w68-process-loader-gold" d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="url(#processLoaderGoldGrad)" stroke-width="16" fill="none" />
                </g>
                <g data-w68-process-loader-pen class="w68-process-loader-pen">
                    <circle data-w68-process-loader-aura cx="0" cy="0" r="14" fill="#FFD700" opacity="0.35" />
                    <circle data-w68-process-loader-dot cx="0" cy="0" r="6" fill="#FFFFFF" stroke="#FFD700" stroke-width="2.5" />
                    <circle data-w68-process-loader-sparkle-one cx="0" cy="0" r="2.5" fill="#FFF2B2" />
                    <circle data-w68-process-loader-sparkle-two cx="0" cy="0" r="2" fill="#D4AF37" />
                </g>
            </svg>
        </div>
    @endunless
</body>
</html>
