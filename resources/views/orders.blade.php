<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>W68 Autoparts | Orders</title>
    <link rel="icon" href="{{ asset('images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-orders.css') }}?v=20260916-v102">
    <link rel="stylesheet" href="{{ asset('css/w68-notifications.css') }}?v=20260916-v102">
    <script src="{{ asset('js/w68-orders.js') }}?v=20260916-v102" defer></script>
    <script src="{{ asset('js/w68-orders-cart.js') }}?v=20260916-v102" defer></script>
    <script src="{{ asset('js/w68-notifications.js') }}?v=20260916-v102" defer></script>
</head>
<body
    data-order-update-base="{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}{{ preg_replace('#/index\.php$#i', '', rtrim(str_replace('\\', '/', (string) request()->getBaseUrl()), '/')) }}/orders"
    data-cart-state-url="{{ route('home.cart.state', [], false) }}"
    data-cart-item-base-url="{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}{{ preg_replace('#/index\.php$#i', '', rtrim(str_replace('\\', '/', (string) request()->getBaseUrl()), '/')) }}/home/cart/items"
    data-cart-selection-url="{{ route('home.cart.selection', [], false) }}"
    data-notifications-url="{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}{{ preg_replace('#/index\.php$#i', '', rtrim(str_replace('\\', '/', (string) request()->getBaseUrl()), '/')) }}/home/notifications"
    data-notifications-read-all-url="{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}{{ preg_replace('#/index\.php$#i', '', rtrim(str_replace('\\', '/', (string) request()->getBaseUrl()), '/')) }}/home/notifications/read-all"
>
@php
    $logo = asset('images/sidebar_logo.png');
    $allOrders = $toShip->concat($received)->concat($cancelled ?? collect())->values();
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
@endphp

<header class="orders-header">
    <div class="orders-header-inner">
        <a href="{{ route('home') }}" class="orders-brand">
            <img src="{{ $logo }}" alt="W68 Autoparts">
            <span><strong>W68 AUTOPARTS</strong><small>MY ORDERS</small></span>
        </a>

        <div class="orders-header-actions">
            <a href="{{ route('home') }}" class="orders-home-button" aria-label="Back to product catalog">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
                <span>SHOP</span>
            </a>
            <button type="button" class="orders-cart-button" data-orders-cart-open aria-label="Open cart" onclick="return window.W68OrdersCartOpen(event)" ontouchstart="return window.W68OrdersCartOpen(event)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20.5 7H6.2M9.5 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg>
                <span>CART</span>
                <b data-orders-cart-count>0</b>
            </button>
            <div class="orders-notification-slot" aria-label="Customer notifications">
                @include('partials.notification-bell')
            </div>
        </div>
    </div>
</header>

<main class="orders-shell">
    <section class="orders-hero">
        <div class="orders-hero-heading">
            <span class="orders-kicker">CUSTOMER ORDER CENTER</span>
            <h1>Orders</h1>
        </div>
        <div class="orders-stats" aria-label="Order dashboard">
            <div class="orders-stat-card orders-stat-to-ship">
                <span class="orders-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                </span>
                <span class="orders-stat-copy"><strong>{{ $toShip->count() }}</strong><span>TO SHIP</span></span>
            </div>
            <div class="orders-stat-card orders-stat-invoiced">
                <span class="orders-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h4"/></svg>
                </span>
                <span class="orders-stat-copy"><strong>{{ $received->count() }}</strong><span>INVOICED</span></span>
            </div>
            <div class="orders-stat-card orders-stat-returns">
                <span class="orders-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 7H4v-4M4 7a8 8 0 1 1-1 8M4 7l4-4"/></svg>
                </span>
                <span class="orders-stat-copy"><strong>{{ $returns->count() }}</strong><span>RETURNS / REFUND</span></span>
            </div>
        </div>
    </section>

    <nav class="orders-tabs" aria-label="Order status tabs">
        <button type="button" class="is-active" data-orders-tab="to-ship">
            <span>1</span> TO SHIP <b>{{ $toShip->count() }}</b>
        </button>
        <button type="button" data-orders-tab="invoiced">
            <span>2</span> INVOICED <b>{{ $received->count() }}</b>
        </button>
        <button type="button" data-orders-tab="returns">
            <span>3</span> RETURNS / REFUND <b>{{ $returns->count() }}</b>
        </button>
        <button type="button" data-orders-tab="cancelled">
            <span>4</span> CANCELLED <b>{{ ($cancelled ?? collect())->count() }}</b>
        </button>
    </nav>

    <section class="orders-tab-panel is-active" data-orders-panel="to-ship">
        <div class="orders-section-heading">
            <div><span>PROCESSED ORDERS</span><h2>To Ship</h2></div>
            <p>These W68 orders have been registered as Sales Notes. Edit actions remain available only while the linked Sales Note is still Open and Sales Order processing has not started.</p>
        </div>

        @forelse ($toShip as $order)
            <article class="order-summary-card">
                <div class="order-summary-main">
                    <div><span>ORDER ID</span><strong>{{ $order['order_code'] }}</strong><small>{{ $order['sales_number'] }}</small></div>
                    <div><span>DATE</span><strong>{{ $order['date'] }}</strong></div>
                    <div><span>TOTAL</span><strong>{{ number_format($order['total_amount'], 2) }}</strong><small>Discount {{ number_format($order['discount_total'], 2) }}</small></div>
                    <div><span>STATUS</span><strong class="status-badge processed">PROCESSED</strong></div>
                </div>
                <a class="view-order-button" href="{{ route('orders.view', ['order' => $order['id']]) }}">VIEW</a>
            </article>
        @empty
            <div class="orders-empty"><strong>No orders waiting to ship.</strong><span>Process selected items from your cart and they will appear here.</span></div>
        @endforelse
    </section>

    <section class="orders-tab-panel" data-orders-panel="invoiced" hidden>
        <div class="orders-section-heading">
            <div><span>CLOSED SALES NOTES</span><h2>Invoiced</h2></div>
            <p>An order moves here as soon as its linked Sales Note becomes <strong>Closed</strong>. If a waybill exists, its details are shown with the order.</p>
        </div>

        @forelse ($received as $order)
            <article class="order-summary-card received-card">
                <div class="order-summary-main">
                    <div><span>ORDER ID</span><strong>{{ $order['order_code'] }}</strong><small>{{ $order['sales_number'] }}</small></div>
                    <div><span>DATE</span><strong>{{ $order['date'] }}</strong></div>
                    <div><span>TOTAL</span><strong>{{ number_format($order['total_amount'], 2) }}</strong></div>
                    <div>
                        <span>STATUS</span>
                        <strong class="status-badge ordered">INVOICED</strong>
                        @if($order['waybill_no'])
                            <small>Waybill {{ $order['waybill_no'] }}@if($order['waybill_date']) Â· {{ $order['waybill_date'] }}@endif</small>
                        @elseif(!empty($order['waybill_id']))
                            <small>Waybill #{{ $order['waybill_id'] }}@if($order['waybill_date']) Â· {{ $order['waybill_date'] }}@endif</small>
                        @endif
                    </div>
                </div>
                <a class="view-order-button" href="{{ route('orders.view', ['order' => $order['id']]) }}">VIEW</a>
            </article>
        @empty
            <div class="orders-empty"><strong>No invoiced orders yet.</strong><span>Closed Sales Notes will automatically move to this tab. Waybill details appear when available.</span></div>
        @endforelse
    </section>

    <section class="orders-tab-panel" data-orders-panel="returns" hidden>
        <div class="orders-section-heading">
            <div><span>RETURNED W68 ORDERS</span><h2>Returns / Refund</h2></div>
            <p>If an invoice created from one of your W68 orders has a Sales Return, that return appears here automatically. VIEW shows only the items that were returned.</p>
        </div>

        @forelse ($returns as $return)
            <article class="order-summary-card return-order-card">
                <div class="order-summary-main">
                    <div>
                        <span>ORDER ID</span>
                        <strong>{{ $return['order_code'] ?: 'â€”' }}</strong>
                        <small>{{ $return['sales_number'] ?: 'â€”' }}</small>
                    </div>
                    <div>
                        <span>RETURN NO.</span>
                        <strong>{{ $return['return_number'] ?: 'RETURN' }}</strong>
                        <small>Invoice {{ $return['invoice_no'] ?: 'â€”' }}</small>
                    </div>
                    <div>
                        <span>DATE</span>
                        <strong>{{ $return['date'] ?: 'â€”' }}</strong>
                        <small>{{ number_format($return['total_items']) }} returned item(s)</small>
                    </div>
                    <div>
                        <span>RETURN TOTAL</span>
                        <strong>{{ number_format($return['total_amount'], 2) }}</strong>
                        <small class="status-badge processed">PROCESSED</small>
                    </div>
                </div>
                <a
                    class="view-order-button"
                    href="{{ route('orders.return.view', ['order' => $return['order_id'], 'return' => $return['return_id']]) }}"
                >VIEW</a>
            </article>
        @empty
            <div class="orders-empty">
                <strong>No returns/refunds for W68 orders.</strong>
                <span>When an invoice belonging to one of your W68 orders has a Sales Return, that returned order will appear here automatically.</span>
            </div>
        @endforelse
    </section>

    <section class="orders-tab-panel" data-orders-panel="cancelled" hidden>
        <div class="orders-section-heading">
            <div><span>CANCELLED PORTAL ORDERS</span><h2>Cancelled</h2></div>
            <p>Only W68 portal orders whose <strong>portal_status</strong> is <strong>CANCELLED</strong> are shown here.</p>
        </div>

        @forelse (($cancelled ?? collect()) as $order)
            <article class="order-summary-card cancelled-card">
                <div class="order-summary-main">
                    <div><span>ORDER ID</span><strong>{{ $order['order_code'] }}</strong><small>{{ $order['sales_number'] }}</small></div>
                    <div><span>DATE</span><strong>{{ $order['date'] }}</strong></div>
                    <div><span>TOTAL</span><strong>{{ number_format($order['total_amount'], 2) }}</strong><small>Discount {{ number_format($order['discount_total'], 2) }}</small></div>
                    <div><span>STATUS</span><strong class="status-badge cancelled">CANCELLED</strong></div>
                </div>
                <a class="view-order-button" href="{{ route('orders.view', ['order' => $order['id']]) }}">VIEW</a>
            </article>
        @empty
            <div class="orders-empty"><strong>No cancelled orders.</strong><span>Orders with portal_status = CANCELLED in w68_portal_orders will appear here.</span></div>
        @endforelse
    </section>

</main>

<aside class="orders-cart-drawer" data-orders-cart-drawer aria-hidden="true">
    <button type="button" class="orders-cart-backdrop" data-orders-cart-close aria-label="Close cart"></button>
    <section class="orders-cart-panel" aria-label="Add to Cart">
        <header class="orders-cart-header">
            <div><span>YOUR ORDER</span><h2>Add to Cart</h2></div>
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

<div class="order-modal" data-order-modal hidden aria-hidden="true">
    <button type="button" class="order-modal-backdrop" data-order-modal-close aria-label="Close order"></button>
    <section class="order-modal-card" role="dialog" aria-modal="true" aria-labelledby="order-modal-title">
        <header class="order-modal-header">
            <div>
                <span>VIEW ORDER</span>
                <h2 id="order-modal-title" data-order-modal-code>Order</h2>
                <p><b>Sales Note:</b> <span data-order-modal-sales-note>â€”</span> &nbsp; <b>Date:</b> <span data-order-modal-date>â€”</span></p>
            </div>
            <button type="button" data-order-modal-close aria-label="Close">&times;</button>
        </header>

        <div class="order-modal-status-row">
            <div><span>STATUS</span><strong data-order-modal-status>PROCESSED</strong></div>
            <div><span>ORDER TOTAL</span><strong data-order-modal-total>0.00</strong></div>
        </div>

        <div class="order-modal-notice" data-order-edit-lock hidden>
            This order is read-only because Sales Order processing has already started or the linked Sales Note is no longer Open.
        </div>

        <div class="order-items-table-wrap">
            <table class="order-items-table">
                <thead>
                    <tr>
                        <th>DESCRIPTION</th>
                        <th>PRODUCT CODE</th>
                        <th>PART NUMBER</th>
                        <th>APPLICATION</th>
                        <th>BRAND</th>
                        <th>PRICE PER PIECE</th>
                        <th>QTY</th>
                        <th>TOTAL PRICE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody data-order-items-body></tbody>
            </table>
        </div>

        <div class="order-modal-error" data-order-modal-error hidden></div>

        <footer class="order-modal-actions">
            <button type="button" class="delete-order-button" data-delete-order>DELETE ORDER</button>
            <button type="button" class="modal-close-button" data-order-modal-close>CLOSE</button>
            <button type="button" class="save-order-button" data-save-order>SAVE CHANGES</button>
        </footer>
    </section>
</div>

<script type="application/json" id="w68-orders-payload">{!! json_encode($allOrders->all(), $jsonFlags) !!}</script>
<script type="application/json" id="w68-orders-cart-payload">{!! json_encode($serverCart ?? [], $jsonFlags) !!}</script>
@include('partials.notification-modal')
</body>
</html>
