<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class CustomerOrderController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $account = Auth::user();

        if (!$account || (int) ($account->account_type ?? 0) !== 5) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        [$account, $loginId, $customerId] = $this->identity($request);
        $this->ensureOrderTables();

        $profileName = trim(
            (string) ($account->User_First_Name ?? '') . ' ' . (string) ($account->User_Last_Name ?? '')
        );
        if ($profileName === '') {
            $profileName = (string) ($account->User_ID ?? 'W68 Customer');
        }

        $orders = $this->portalOrders($loginId, $customerId);
        $cancelled = $orders->where('cancelled', true)->values();
        $activeOrders = $orders->where('cancelled', false)->values();
        $toShip = $activeOrders->where('received', false)->values();
        $received = $activeOrders->where('received', true)->values();
        $returns = $this->portalReturns($activeOrders, $customerId);

        // Load the same account-specific cart directly on the Orders request.
        // This keeps the cart visible even when Safari/iPad blocks or delays
        // the follow-up AJAX request used to refresh the drawer.
        $serverCart = $this->portalCartItems($loginId, $customerId);

        return view('orders', [
            'account' => $account,
            'profileName' => $profileName,
            'toShip' => $toShip,
            'received' => $received,
            'returns' => $returns,
            'cancelled' => $cancelled,
            'serverCart' => $serverCart,
        ]);
    }

    public function processOrderPage(Request $request): View|RedirectResponse
    {
        $account = Auth::user();

        if (!$account || (int) ($account->account_type ?? 0) !== 5) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureCartTable();

        $items = collect($this->portalCartItems($loginId, $customerId))
            ->filter(fn (array $item): bool => !empty($item['selected']))
            ->map(function (array $item): array {
                $qty = max(1, min(9999, (int) ($item['qty'] ?? 1)));
                $originalPrice = round((float) ($item['price'] ?? 0), 2);
                $discountedPrice = round((float) ($item['discountedPrice'] ?? $originalPrice), 2);
                $discountPercent = max(0, min(100, (float) ($item['discountPercent'] ?? 0)));

                $item['qty'] = $qty;
                $item['price'] = $originalPrice;
                $item['discountedPrice'] = $discountedPrice;
                $item['discountPercent'] = $discountPercent;
                $item['lineTotal'] = round($discountedPrice * $qty, 2);
                $item['hasDiscount'] = $discountPercent > 0.0001 || ($discountedPrice + 0.0001) < $originalPrice;

                return $item;
            })
            ->values();

        $totalItems = (int) $items->sum(fn (array $item): int => (int) $item['qty']);
        $totalPrice = round((float) $items->sum(fn (array $item): float => (float) $item['lineTotal']), 2);

        return view('process-order', [
            'items' => $items,
            'totalItems' => $totalItems,
            'totalPrice' => $totalPrice,
            // W68 v106: only Rush/Regular shipment choices from Masterlist.
            'shipments' => $this->shipmentForwarders(),
        ]);
    }

    public function viewOrder(Request $request, int $order): View|RedirectResponse
    {
        $account = Auth::user();

        if (!$account || (int) ($account->account_type ?? 0) !== 5) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureOrderTables();

        $orderData = $this->portalOrders($loginId, $customerId)
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $order);

        abort_unless($orderData, 404, 'Order not found.');

        $items = collect($orderData['items'] ?? [])->map(function (array $item): array {
            $qty = max(1, min(9999, (int) ($item['quantity'] ?? 1)));
            $originalPrice = round((float) ($item['original_unit_price'] ?? 0), 2);
            $discountedPrice = round((float) ($item['discounted_unit_price'] ?? $originalPrice), 2);
            $discountPercent = max(0, min(100, (float) ($item['discount_percent'] ?? 0)));
            $lineTotal = round((float) ($item['total_price'] ?? ($discountedPrice * $qty)), 2);

            return [
                'id' => (string) ((int) ($item['product_id'] ?? 0)),
                'image' => $this->currentProductImageUrl((int) ($item['product_id'] ?? 0)),
                'productCode' => (string) ($item['product_code'] ?? ''),
                'partNumber' => (string) ($item['part_number'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'application' => (string) ($item['application'] ?? ''),
                'position' => (string) ($item['position'] ?? ''),
                'brand' => (string) ($item['brand'] ?? ''),
                'price' => $originalPrice,
                'discountPercent' => $discountPercent,
                'discountedPrice' => $discountedPrice,
                'qty' => $qty,
                'lineTotal' => $lineTotal,
                'hasDiscount' => $discountPercent > 0.0001 || ($discountedPrice + 0.0001) < $originalPrice,
            ];
        })->values();

        $totalItems = (int) $items->sum(fn (array $item): int => (int) $item['qty']);
        $totalPrice = round((float) $items->sum(fn (array $item): float => (float) $item['lineTotal']), 2);

        return view('process-order', [
            'items' => $items,
            'totalItems' => $totalItems,
            'totalPrice' => $totalPrice,
            'viewMode' => true,
            'viewOrder' => $orderData,
        ]);
    }

    public function viewReturn(Request $request, int $order, int $return): View|RedirectResponse
    {
        $account = Auth::user();

        if (!$account || (int) ($account->account_type ?? 0) !== 5) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureOrderTables();

        $orderData = $this->portalOrders($loginId, $customerId)
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $order);

        abort_unless($orderData, 404, 'Order not found.');

        $returnData = $this->portalReturns(collect([$orderData]), $customerId)
            ->first(fn (array $row): bool => (int) ($row['return_id'] ?? 0) === $return);

        abort_unless($returnData, 404, 'Return not found for this W68 order.');

        $items = collect($returnData['items'] ?? [])->map(function (array $item): array {
            $qty = max(1, min(9999, (int) ($item['quantity'] ?? 1)));
            $originalPrice = round((float) ($item['original_unit_price'] ?? 0), 2);
            $discountedPrice = round((float) ($item['discounted_unit_price'] ?? $originalPrice), 2);
            $discountPercent = max(0, min(100, (float) ($item['discount_percent'] ?? 0)));
            $lineTotal = round((float) ($item['total_price'] ?? ($discountedPrice * $qty)), 2);

            return [
                'id' => (string) ((int) ($item['product_id'] ?? 0)),
                'image' => $this->currentProductImageUrl((int) ($item['product_id'] ?? 0)),
                'productCode' => (string) ($item['product_code'] ?? ''),
                'partNumber' => (string) ($item['part_number'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'application' => (string) ($item['application'] ?? ''),
                'position' => '',
                'brand' => (string) ($item['brand'] ?? ''),
                'price' => $originalPrice,
                'discountPercent' => $discountPercent,
                'discountedPrice' => $discountedPrice,
                'qty' => $qty,
                'lineTotal' => $lineTotal,
                'hasDiscount' => $discountPercent > 0.0001 || ($discountedPrice + 0.0001) < $originalPrice,
            ];
        })->values();

        $totalItems = (int) $items->sum(fn (array $item): int => (int) $item['qty']);
        $totalPrice = round((float) $items->sum(fn (array $item): float => (float) $item['lineTotal']), 2);

        return view('process-order', [
            'items' => $items,
            'totalItems' => $totalItems,
            'totalPrice' => $totalPrice,
            'viewMode' => true,
            'returnViewMode' => true,
            'viewOrder' => [
                'order_code' => (string) ($returnData['return_number'] ?: ('RETURN-' . $returnData['return_id'])),
                'sales_number' => (string) ($orderData['sales_number'] ?? ''),
                'date' => (string) ($returnData['date'] ?? ''),
                'status_label' => 'RETURNED',
                'invoice_no' => (string) ($returnData['invoice_no'] ?? ''),
                'portal_order_code' => (string) ($orderData['order_code'] ?? ''),
            ],
        ]);
    }

    public function processSelectedCart(Request $request): JsonResponse
    {
        [$account, $loginId, $customerId] = $this->identity($request);
        $this->ensureOrderTables();
        $this->ensureCartTable();
        abort_unless(
            Schema::connection('sales_order')->hasColumn('w68_portal_order_items', 'cart_id'),
            503,
            'The W68 cart-to-order link is not installed yet. Run the v93 cart_id database patch first.'
        );

        $delivery = $request->validate([
            'forwarder_id' => ['required', 'integer', 'min:1'],
        ], [
            'forwarder_id.required' => 'Choose a Shipment before processing the order.',
        ]);

        abort_unless(
            Schema::connection('masterlist')->hasTable('forwarders')
            && Schema::connection('masterlist')->hasColumn('forwarders', 'forwarder_type'),
            503,
            'Shipment setup is not available in W68 Masterlist yet.'
        );

        // W68 v107: the customer chooses the forwarder directly. Rush/Regular
        // is derived from forwarders.forwarder_type instead of being submitted
        // separately by the browser. This prevents mismatched delivery types.
        $shipment = DB::connection('masterlist')
            ->table('forwarders')
            ->where('id', (int) $delivery['forwarder_id'])
            ->whereNotNull('forwarder_type')
            ->whereRaw('LOWER(TRIM(forwarder_type)) IN (?, ?)', ['rush', 'regular'])
            ->first(['id', 'name', 'forwarder_type']);

        abort_unless($shipment, 422, 'The selected Shipment is not available as Rush or Regular.');

        $shipmentId = (int) $shipment->id;
        $shipmentName = trim((string) ($shipment->name ?? ''));
        $deliveryOption = mb_strtolower(trim((string) ($shipment->forwarder_type ?? '')));
        abort_if($shipmentName === '', 422, 'The selected Shipment is no longer available.');
        abort_unless(in_array($deliveryOption, ['rush', 'regular'], true), 422, 'The selected Shipment must be Rush or Regular.');

        $deliveryLabel = $deliveryOption === 'rush' ? 'RUSH' : 'REGULAR';
        // Keep the existing [Forwarder: ...] remark token for compatibility with
        // W68 downstream Sales Order / Waybill logic while the customer UI says Shipment.
        $shipmentRemark = 'DELIVERY: ' . $deliveryLabel . ' | [Forwarder: ' . $shipmentName . ']';

        $processedCartIds = $this->processedCartIds($loginId, $customerId);

        $cartQuery = DB::connection('sales_order')
            ->table('w68_customer_cart_items')
            ->where('login_id', $loginId)
            ->where('customer_id', $customerId)
            ->where('is_selected', 1);

        if ($processedCartIds->isNotEmpty()) {
            $cartQuery->whereNotIn('id', $processedCartIds->all());
        }

        $cartRows = $cartQuery
            ->orderBy('id')
            ->get(['id', 'product_id', 'quantity']);

        abort_if($cartRows->isEmpty(), 422, 'Select at least one cart item before processing the order.');

        $customer = DB::connection('masterlist')
            ->table('customers')
            ->where('id', $customerId)
            ->first(['id', 'name']);

        abort_unless($customer, 422, 'The customer linked to this W68 account was not found.');

        $productIds = $cartRows->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $products = DB::connection('masterlist')
            ->table('products')
            ->whereIn('id', $productIds)
            ->where('is_selected_for_report', 1)
            ->get([
                'id',
                'product_code',
                'part_number',
                'description',
                'application',
                'Position as position',
                'category as brand',
                'unit',
                'selling_price',
            ])
            ->keyBy('id');

        abort_unless($products->count() === count(array_unique($productIds)), 422, 'One or more selected cart products are no longer available.');

        $discounts = $this->customerBrandDiscounts($customerId);
        $lines = collect();

        foreach ($cartRows as $cartRow) {
            $product = $products->get((int) $cartRow->product_id);
            if (!$product) {
                abort(422, 'One or more selected cart products are no longer available.');
            }

            $qty = max(1, min(9999, (int) $cartRow->quantity));
            $originalPrice = round((float) ($product->selling_price ?? 0), 2);
            $discountPercent = $discounts[$this->normalizeBrand((string) ($product->brand ?? ''))] ?? 0.0;
            $discountPercent = max(0, min(100, (float) $discountPercent));
            $discountedPrice = round($originalPrice * (1 - ($discountPercent / 100)), 2);
            $lineTotal = round($discountedPrice * $qty, 2);

            $lines->push([
                'cart_id' => (int) $cartRow->id,
                'product_id' => (int) $product->id,
                'product_code' => (string) ($product->product_code ?? ''),
                'part_number' => (string) ($product->part_number ?? ''),
                'description' => (string) ($product->description ?? ''),
                'application' => (string) ($product->application ?? ''),
                'position' => (string) ($product->position ?? ''),
                'brand' => (string) ($product->brand ?? ''),
                'oum' => trim((string) ($product->unit ?? '')),
                'original_unit_price' => $originalPrice,
                'discount_percent' => $discountPercent,
                'discounted_unit_price' => $discountedPrice,
                'quantity' => $qty,
                'total_price' => $lineTotal,
            ]);
        }

        // W68 portal checkout intentionally allows back-orders.
        // Do not block creation of an Open Sales Note when ledger stock is zero or negative.
        // Stock checks remain unchanged for the separate existing-order edit flow below.

        $grossTotal = round((float) $lines->sum(fn ($line) => $line['original_unit_price'] * $line['quantity']), 2);
        $netTotal = round((float) $lines->sum('total_price'), 2);
        $totalDiscount = round(max(0, $grossTotal - $netTotal), 2);
        $preparedBy = $this->accountDisplayName($account);
        $salesNoteItemsHaveProductCode = Schema::connection('sales_order')->hasColumn('sales_note_items', 'product_code');

        $created = DB::connection('sales_order')->transaction(function () use (
            $loginId,
            $customerId,
            $customer,
            $lines,
            $grossTotal,
            $totalDiscount,
            $netTotal,
            $preparedBy,
            $salesNoteItemsHaveProductCode,
            $deliveryOption,
            $shipmentRemark
        ): array {
            $salesNumber = $this->nextSalesNumber();
            $now = now();

            $salesNoteId = DB::connection('sales_order')->table('sales_notes')->insertGetId([
                'sales_number' => $salesNumber,
                'so_type' => 'Sales Order',
                'customer_id' => $customerId,
                'customer_name' => (string) $customer->name,
                'order_date' => $now->toDateString(),
                'salesman' => null,
                'prepared_by' => $preparedBy,
                'checked_by' => null,
                'packed_by' => null,
                'is_rush' => $deliveryOption === 'rush' ? 1 : 0,
                'gross_total' => $grossTotal,
                'total_discount' => $totalDiscount,
                'net_total' => $netTotal,
                'status' => 'Open',
                'remarks' => 'W68 PRICELIST PORTAL ORDER | ' . $shipmentRemark,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $portalOrderId = DB::connection('sales_order')->table('w68_portal_orders')->insertGetId([
                'order_code' => null,
                'login_id' => $loginId,
                'customer_id' => $customerId,
                'sales_note_id' => $salesNoteId,
                'sales_number' => $salesNumber,
                'original_total' => $grossTotal,
                'discount_total' => $totalDiscount,
                'total_amount' => $netTotal,
                'portal_status' => 'Processed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $orderCode = 'W68-' . str_pad((string) $portalOrderId, 8, '0', STR_PAD_LEFT);

            DB::connection('sales_order')->table('w68_portal_orders')
                ->where('id', $portalOrderId)
                ->update(['order_code' => $orderCode, 'updated_at' => $now]);

            DB::connection('sales_order')->table('sales_notes')
                ->where('id', $salesNoteId)
                ->update([
                    'remarks' => 'W68 PRICELIST PORTAL ORDER | ' . $orderCode . ' | ' . $shipmentRemark,
                    'updated_at' => $now,
                ]);

            foreach ($lines as $line) {
                $salesNoteItem = [
                    'sales_note_id' => $salesNoteId,
                    'product_id' => $line['product_id'],
                    'price_code' => null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'additional_qty' => 0,
                    'oum' => $line['oum'],
                    'unit_price' => $line['original_unit_price'],
                    'discount' => $line['discount_percent'],
                    'bonus' => 0,
                    'subtotal' => $line['total_price'],
                    'particulars' => 'W68 ONLINE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($salesNoteItemsHaveProductCode) {
                    $salesNoteItem['product_code'] = $line['product_code'];
                }

                $salesNoteItemId = DB::connection('sales_order')
                    ->table('sales_note_items')
                    ->insertGetId($salesNoteItem);

                DB::connection('sales_order')->table('w68_portal_order_items')->insert([
                    'w68_portal_order_id' => $portalOrderId,
                    'cart_id' => $line['cart_id'],
                    'sales_note_item_id' => $salesNoteItemId,
                    'product_id' => $line['product_id'],
                    'product_code' => $line['product_code'],
                    'part_number' => $line['part_number'],
                    'description' => $line['description'],
                    'application' => $line['application'],
                    'position' => $line['position'],
                    'brand' => $line['brand'],
                    'original_unit_price' => $line['original_unit_price'],
                    'discount_percent' => $line['discount_percent'],
                    'discounted_unit_price' => $line['discounted_unit_price'],
                    'quantity' => $line['quantity'],
                    'total_price' => $line['total_price'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::connection('sales_order')->table('w68_customer_cart_items')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId)
                ->where('is_selected', 1)
                ->delete();

            return [
                'order_id' => $portalOrderId,
                'order_code' => $orderCode,
                'sales_note_id' => $salesNoteId,
                'sales_number' => $salesNumber,
                'total' => $netTotal,
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => 'Order processed and registered as an Open Sales Note.',
            'redirect_url' => ltrim(route('orders', [], false), '/'),
            'delivery_option' => $deliveryLabel,
            'shipment_id' => $shipmentId,
            'shipment_name' => $shipmentName,
            ...$created,
        ]);
    }

    public function updateOrder(Request $request, int $order): JsonResponse
    {
        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureOrderTables();

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $portalOrder = $this->ownedOrder($order, $loginId, $customerId);
        $this->assertOrderEditable($portalOrder);

        $orderItems = DB::connection('sales_order')
            ->table('w68_portal_order_items')
            ->where('w68_portal_order_id', $portalOrder->id)
            ->get()
            ->keyBy('id');

        $requested = collect($validated['items']);
        abort_if($requested->count() !== $orderItems->count(), 422, 'All current order items must be included when saving changes.');

        $requestedByProduct = [];
        $metaByProduct = [];

        foreach ($requested as $row) {
            $item = $orderItems->get((int) $row['id']);
            abort_unless($item, 422, 'An order item no longer exists. Refresh the Orders page.');

            $requestedByProduct[(int) $item->product_id] = (int) $row['quantity'];
            $metaByProduct[(int) $item->product_id] = [
                'product_code' => (string) $item->product_code,
                'description' => (string) $item->description,
            ];
        }

        $this->assertStockAvailable($requestedByProduct, $metaByProduct);

        DB::connection('sales_order')->transaction(function () use ($portalOrder, $requested, $orderItems): void {
            $now = now();

            foreach ($requested as $row) {
                $item = $orderItems->get((int) $row['id']);
                $quantity = (int) $row['quantity'];
                $total = round((float) $item->discounted_unit_price * $quantity, 2);

                DB::connection('sales_order')->table('w68_portal_order_items')
                    ->where('id', $item->id)
                    ->where('w68_portal_order_id', $portalOrder->id)
                    ->update([
                        'quantity' => $quantity,
                        'total_price' => $total,
                        'updated_at' => $now,
                    ]);

                if ($item->sales_note_item_id) {
                    DB::connection('sales_order')->table('sales_note_items')
                        ->where('id', $item->sales_note_item_id)
                        ->where('sales_note_id', $portalOrder->sales_note_id)
                        ->update([
                            'quantity' => $quantity,
                            'subtotal' => $total,
                            'updated_at' => $now,
                        ]);
                }
            }

            $this->recalculateOrderTotals((int) $portalOrder->id, (int) $portalOrder->sales_note_id, $now);
        });

        return response()->json(['ok' => true, 'message' => 'Order changes saved.']);
    }

    public function deleteItem(Request $request, int $order, int $item): JsonResponse
    {
        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureOrderTables();

        $portalOrder = $this->ownedOrder($order, $loginId, $customerId);
        $this->assertOrderEditable($portalOrder);

        $portalItem = DB::connection('sales_order')
            ->table('w68_portal_order_items')
            ->where('id', $item)
            ->where('w68_portal_order_id', $portalOrder->id)
            ->first();

        abort_unless($portalItem, 404, 'Order item not found.');

        $orderDeleted = DB::connection('sales_order')->transaction(function () use ($portalOrder, $portalItem): bool {
            if ($portalItem->sales_note_item_id) {
                DB::connection('sales_order')->table('sales_note_items')
                    ->where('id', $portalItem->sales_note_item_id)
                    ->where('sales_note_id', $portalOrder->sales_note_id)
                    ->delete();
            }

            DB::connection('sales_order')->table('w68_portal_order_items')
                ->where('id', $portalItem->id)
                ->where('w68_portal_order_id', $portalOrder->id)
                ->delete();

            $remaining = DB::connection('sales_order')->table('w68_portal_order_items')
                ->where('w68_portal_order_id', $portalOrder->id)
                ->count();

            if ($remaining === 0) {
                DB::connection('sales_order')->table('sales_note_items')
                    ->where('sales_note_id', $portalOrder->sales_note_id)
                    ->delete();
                DB::connection('sales_order')->table('sales_notes')
                    ->where('id', $portalOrder->sales_note_id)
                    ->delete();
                DB::connection('sales_order')->table('w68_portal_orders')
                    ->where('id', $portalOrder->id)
                    ->delete();

                return true;
            }

            $this->recalculateOrderTotals((int) $portalOrder->id, (int) $portalOrder->sales_note_id, now());
            return false;
        });

        return response()->json([
            'ok' => true,
            'order_deleted' => $orderDeleted,
            'message' => $orderDeleted ? 'The final item was removed, so the order was deleted.' : 'Item deleted from the order.',
        ]);
    }

    public function deleteOrder(Request $request, int $order): JsonResponse
    {
        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureOrderTables();

        $portalOrder = $this->ownedOrder($order, $loginId, $customerId);
        $this->assertOrderEditable($portalOrder);

        DB::connection('sales_order')->transaction(function () use ($portalOrder): void {
            DB::connection('sales_order')->table('w68_portal_order_items')
                ->where('w68_portal_order_id', $portalOrder->id)
                ->delete();

            DB::connection('sales_order')->table('sales_note_items')
                ->where('sales_note_id', $portalOrder->sales_note_id)
                ->delete();

            DB::connection('sales_order')->table('sales_notes')
                ->where('id', $portalOrder->sales_note_id)
                ->delete();

            DB::connection('sales_order')->table('w68_portal_orders')
                ->where('id', $portalOrder->id)
                ->delete();
        });

        return response()->json(['ok' => true, 'message' => 'Order deleted.']);
    }

    private function portalOrders(int $loginId, int $customerId): Collection
    {
        $rows = DB::connection('sales_order')
            ->table('w68_portal_orders as po')
            ->leftJoin('sales_notes as sn', 'sn.id', '=', 'po.sales_note_id')
            ->where('po.login_id', $loginId)
            ->where('po.customer_id', $customerId)
            ->orderByDesc('po.id')
            ->get([
                'po.id',
                'po.order_code',
                'po.sales_note_id',
                'po.sales_number',
                'po.original_total',
                'po.discount_total',
                'po.total_amount',
                'po.portal_status',
                'po.created_at',
                'sn.status as sales_note_status',
                'sn.order_date',
            ]);

        if ($rows->isEmpty()) {
            return collect();
        }

        $orderIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $noteIds = $rows->pluck('sales_note_id')->filter()->map(fn ($id) => (int) $id)->all();

        $itemsByOrder = DB::connection('sales_order')
            ->table('w68_portal_order_items')
            ->whereIn('w68_portal_order_id', $orderIds)
            ->orderBy('id')
            ->get()
            ->groupBy('w68_portal_order_id');

        $downstream = empty($noteIds)
            ? collect()
            : DB::connection('sales_order')
                ->table('sales_orders')
                ->whereIn('sales_note_id', $noteIds)
                ->orderBy('id')
                ->get(['id', 'sales_note_id', 'invoice_numbers', 'waybill_no', 'waybill_id', 'waybill_date', 'status'])
                ->groupBy('sales_note_id');

        return $rows->map(function ($row) use ($itemsByOrder, $downstream) {
            $salesOrders = $downstream->get($row->sales_note_id, collect());
            $portalStatus = mb_strtoupper(trim((string) ($row->portal_status ?? '')));
            $cancelled = $portalStatus === 'CANCELLED';
            $closed = mb_strtolower(trim((string) ($row->sales_note_status ?? ''))) === 'closed';
            // A W68 order moves to INVOICED as soon as its linked Sales Note is Closed.
            // A waybill is informational only and is no longer required for the tab movement.
            $received = !$cancelled && $closed;
            $editable = !$cancelled
                && mb_strtolower(trim((string) ($row->sales_note_status ?? ''))) === 'open'
                && $salesOrders->isEmpty();
            $waybillRow = $salesOrders->first(function ($salesOrder) {
                return !empty($salesOrder->waybill_id)
                    || trim((string) ($salesOrder->waybill_no ?? '')) !== '';
            });

            $items = $itemsByOrder->get($row->id, collect())->map(fn ($item) => [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'description' => (string) ($item->description ?? ''),
                'product_code' => (string) ($item->product_code ?? ''),
                'part_number' => (string) ($item->part_number ?? ''),
                'application' => (string) ($item->application ?? ''),
                'position' => (string) ($item->position ?? ''),
                'brand' => (string) ($item->brand ?? ''),
                'original_unit_price' => (float) $item->original_unit_price,
                'discount_percent' => (float) $item->discount_percent,
                'discounted_unit_price' => (float) $item->discounted_unit_price,
                'quantity' => (int) $item->quantity,
                'total_price' => (float) $item->total_price,
            ])->values()->all();

            return [
                'id' => (int) $row->id,
                'order_code' => (string) ($row->order_code ?: ('W68-' . str_pad((string) $row->id, 8, '0', STR_PAD_LEFT))),
                'sales_note_id' => (int) ($row->sales_note_id ?? 0),
                'sales_number' => (string) ($row->sales_number ?? ''),
                'date' => (string) ($row->order_date ?: substr((string) $row->created_at, 0, 10)),
                'original_total' => (float) $row->original_total,
                'discount_total' => (float) $row->discount_total,
                'total_amount' => (float) $row->total_amount,
                'sales_note_status' => (string) ($row->sales_note_status ?? 'Open'),
                'portal_status' => (string) ($row->portal_status ?? ''),
                'status_label' => $cancelled ? 'CANCELLED' : ($received ? 'ORDERED' : 'PROCESSED'),
                'cancelled' => $cancelled,
                'received' => $received,
                'editable' => $editable,
                'waybill_no' => (string) ($waybillRow->waybill_no ?? ''),
                'waybill_id' => (int) ($waybillRow->waybill_id ?? 0),
                'waybill_date' => (string) ($waybillRow->waybill_date ?? ''),
                'items' => $items,
            ];
        });
    }

    private function portalReturns(Collection $orders, int $customerId): Collection
    {
        $ordersByNote = $orders
            ->filter(fn (array $order): bool => !empty($order['sales_note_id']))
            ->keyBy(fn (array $order): int => (int) $order['sales_note_id']);

        $noteIds = $ordersByNote->keys()->map(fn ($id) => (int) $id)->all();
        if ($noteIds === []) {
            return collect();
        }

        $salesOrders = DB::connection('sales_order')
            ->table('sales_orders')
            ->whereIn('sales_note_id', $noteIds)
            ->get(['id', 'sales_note_id', 'invoice_numbers']);

        $invoiceToOrder = collect();
        foreach ($salesOrders as $salesOrder) {
            $portalOrder = $ordersByNote->get((int) $salesOrder->sales_note_id);
            if (!$portalOrder) {
                continue;
            }

            foreach ($this->splitInvoiceNumbers($salesOrder->invoice_numbers) as $invoice) {
                $invoice = trim((string) $invoice);
                if ($invoice === '') {
                    continue;
                }

                $invoiceToOrder->put(mb_strtoupper($invoice), $portalOrder);
            }
        }

        if ($invoiceToOrder->isEmpty()) {
            return collect();
        }

        $invoiceNumbers = $invoiceToOrder->keys()->values()->all();

        $returns = DB::connection('sales_order')
            ->table('sales_returns')
            ->where('customer_id', $customerId)
            ->whereIn(DB::raw('UPPER(TRIM(invoice_no))'), $invoiceNumbers)
            ->orderByDesc('id')
            ->get([
                'id',
                'return_number',
                'invoice_no',
                'total_items',
                'total_amount',
                'status',
                'created_at',
            ]);

        if ($returns->isEmpty()) {
            return collect();
        }

        $returnItems = DB::connection('sales_order')
            ->table('sales_return_items')
            ->whereIn('sales_return_id', $returns->pluck('id')->all())
            ->orderBy('id')
            ->get();

        $brands = collect();
        $productIds = $returnItems->pluck('product_id')->filter()->unique()->values()->all();
        if ($productIds !== []) {
            $brands = DB::connection('masterlist')
                ->table('products')
                ->whereIn('id', $productIds)
                ->pluck('category', 'id');
        }

        $itemsByReturn = $returnItems->groupBy('sales_return_id');

        return $returns->map(function ($return) use ($invoiceToOrder, $itemsByReturn, $brands) {
            $invoiceKey = mb_strtoupper(trim((string) ($return->invoice_no ?? '')));
            $portalOrder = $invoiceToOrder->get($invoiceKey);
            if (!$portalOrder) {
                return null;
            }

            $items = $itemsByReturn->get($return->id, collect())->map(function ($item) use ($brands) {
                $unitPrice = round((float) ($item->unit_price ?? 0), 2);
                $discount = max(0, min(100, (float) ($item->discount ?? 0)));
                $effective = round($unitPrice * (1 - ($discount / 100)), 2);
                $quantity = max(0, (int) ($item->quantity ?? 0));
                $total = (float) ($item->return_amount ?? 0);
                if ($total <= 0) {
                    $total = (float) ($item->subtotal ?? 0);
                }
                if ($total <= 0) {
                    $total = round($effective * $quantity, 2);
                }

                return [
                    'id' => (int) ($item->id ?? 0),
                    'product_id' => (int) ($item->product_id ?? 0),
                    'description' => (string) ($item->description ?? ''),
                    'product_code' => (string) ($item->product_code ?? ''),
                    'part_number' => (string) ($item->part_number ?? ''),
                    'application' => (string) ($item->application ?? ''),
                    'brand' => (string) ($brands[(int) ($item->product_id ?? 0)] ?? ''),
                    'original_unit_price' => $unitPrice,
                    'discount_percent' => $discount,
                    'discounted_unit_price' => $effective,
                    'quantity' => $quantity,
                    'total_price' => round($total, 2),
                ];
            })->values();

            $computedTotal = round((float) $items->sum('total_price'), 2);
            $computedQty = (int) $items->sum('quantity');

            return [
                'return_id' => (int) $return->id,
                'return_number' => (string) ($return->return_number ?? ''),
                'invoice_no' => (string) ($return->invoice_no ?? ''),
                'date' => substr((string) ($return->created_at ?? ''), 0, 10),
                'status_label' => 'PROCESSED',
                'return_status' => (string) ($return->status ?? ''),
                'order_id' => (int) ($portalOrder['id'] ?? 0),
                'order_code' => (string) ($portalOrder['order_code'] ?? ''),
                'sales_number' => (string) ($portalOrder['sales_number'] ?? ''),
                'total_items' => (int) (($return->total_items ?? 0) ?: $computedQty),
                'total_amount' => round((float) (($return->total_amount ?? 0) ?: $computedTotal), 2),
                'items' => $items->all(),
            ];
        })->filter()->values();
    }

    private function ownedOrder(int $order, int $loginId, int $customerId): object
    {
        $row = DB::connection('sales_order')
            ->table('w68_portal_orders')
            ->where('id', $order)
            ->where('login_id', $loginId)
            ->where('customer_id', $customerId)
            ->first();

        abort_unless($row, 404, 'Order not found for this account.');
        return $row;
    }

    private function assertOrderEditable(object $portalOrder): void
    {
        $note = DB::connection('sales_order')
            ->table('sales_notes')
            ->where('id', $portalOrder->sales_note_id)
            ->first(['id', 'status']);

        abort_unless($note, 409, 'The linked Sales Note no longer exists.');
        abort_unless(
            mb_strtolower(trim((string) $note->status)) === 'open',
            409,
            'This order can no longer be edited because its Sales Note is no longer Open.'
        );

        $hasSalesOrder = DB::connection('sales_order')
            ->table('sales_orders')
            ->where('sales_note_id', $portalOrder->sales_note_id)
            ->exists();

        abort_if($hasSalesOrder, 409, 'This order can no longer be edited because Sales Order processing has already started.');
    }

    private function recalculateOrderTotals(int $portalOrderId, int $salesNoteId, $now): void
    {
        $items = DB::connection('sales_order')
            ->table('w68_portal_order_items')
            ->where('w68_portal_order_id', $portalOrderId)
            ->get(['original_unit_price', 'discounted_unit_price', 'quantity']);

        $gross = round((float) $items->sum(fn ($item) => (float) $item->original_unit_price * (int) $item->quantity), 2);
        $net = round((float) $items->sum(fn ($item) => (float) $item->discounted_unit_price * (int) $item->quantity), 2);
        $discount = round(max(0, $gross - $net), 2);

        DB::connection('sales_order')->table('w68_portal_orders')
            ->where('id', $portalOrderId)
            ->update([
                'original_total' => $gross,
                'discount_total' => $discount,
                'total_amount' => $net,
                'updated_at' => $now,
            ]);

        DB::connection('sales_order')->table('sales_notes')
            ->where('id', $salesNoteId)
            ->update([
                'gross_total' => $gross,
                'total_discount' => $discount,
                'net_total' => $net,
                'updated_at' => $now,
            ]);
    }

    private function nextSalesNumber(): string
    {
        $maxNum = DB::connection('sales_order')
            ->table('sales_notes')
            ->where(function ($query) {
                $query->whereRaw("sales_number REGEXP '^000[0-9]{5}$'")
                    ->orWhereRaw("sales_number REGEXP '^SN-000[0-9]{5}$'");
            })
            ->selectRaw("COALESCE(MAX(CAST(REPLACE(sales_number, 'SN-', '') AS UNSIGNED)), 0) as max_num")
            ->value('max_num');

        do {
            $next = ((int) $maxNum) + 1;
            $salesNumber = 'SN-' . str_pad((string) $next, 7, '0', STR_PAD_LEFT);
            $maxNum = $next;
        } while (DB::connection('sales_order')->table('sales_notes')->where('sales_number', $salesNumber)->exists());

        return $salesNumber;
    }

    private function assertStockAvailable(array $requestedByProduct, array $metaByProduct): void
    {
        if ($requestedByProduct === []) {
            abort(422, 'No valid order items were supplied.');
        }

        try {
            $productIds = array_map('intval', array_keys($requestedByProduct));

            $latestCreatedAtQuery = DB::connection('ledger')
                ->table('product_ledgers as pl')
                ->selectRaw('pl.product_id, MAX(pl.created_at) as max_created_at')
                ->whereIn('pl.product_id', $productIds)
                ->groupBy('pl.product_id');

            $latestIdsQuery = DB::connection('ledger')
                ->table('product_ledgers as pl')
                ->joinSub($latestCreatedAtQuery, 'lc', function ($join) {
                    $join->on('pl.product_id', '=', 'lc.product_id')
                        ->on('pl.created_at', '=', 'lc.max_created_at');
                })
                ->selectRaw('MAX(pl.id) as id')
                ->groupBy('pl.product_id');

            $latestRows = DB::connection('ledger')
                ->table('product_ledgers')
                ->whereIn('id', $latestIdsQuery)
                ->get(['product_id', 'balance_stock']);

            $balances = [];
            foreach ($latestRows as $row) {
                $balances[(int) $row->product_id] = (float) ($row->balance_stock ?? 0);
            }

            $issues = [];
            foreach ($requestedByProduct as $productId => $quantity) {
                $productId = (int) $productId;
                $requested = (int) $quantity;
                $available = (float) ($balances[$productId] ?? 0);

                if ($available < $requested) {
                    $meta = $metaByProduct[$productId] ?? [];
                    $issues[] = trim((string) ($meta['product_code'] ?? ('Product #' . $productId)))
                        . ': requested ' . $requested . ', available ' . rtrim(rtrim(number_format($available, 2, '.', ''), '0'), '.');
                }
            }

            abort_if($issues !== [], 422, "Stock is not enough for:\n" . implode("\n", $issues));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'Unable to verify stock from the product ledger. Please try again.');
        }
    }

    private function portalCartItems(int $loginId, int $customerId): array
    {
        try {
            if (!Schema::connection('sales_order')->hasTable('w68_customer_cart_items')) {
                return [];
            }

            $processedCartIds = $this->processedCartIds($loginId, $customerId);

            $cartQuery = DB::connection('sales_order')
                ->table('w68_customer_cart_items')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId);

            if ($processedCartIds->isNotEmpty()) {
                $cartQuery->whereNotIn('id', $processedCartIds->all());
            }

            $cartRows = $cartQuery
                ->orderBy('id')
                ->get(['id', 'product_id', 'quantity', 'is_selected']);

            if ($cartRows->isEmpty()) {
                return [];
            }

            $products = DB::connection('masterlist')
                ->table('products as p')
                ->whereIn('p.id', $cartRows->pluck('product_id')->all())
                ->select([
                    'p.id',
                    'p.product_code',
                    'p.part_number',
                    'p.description',
                    'p.category as brand',
                    'p.application',
                    'p.specification',
                    'p.Position as position',
                    'p.selling_price',
                ])
                ->get()
                ->keyBy('id');

            $discounts = $this->customerBrandDiscounts($customerId);

            return $cartRows
                ->map(function ($cartRow) use ($products, $discounts) {
                    $product = $products->get((int) $cartRow->product_id);
                    if (!$product) {
                        return null;
                    }

                    $originalPrice = round((float) ($product->selling_price ?? 0), 2);
                    $discountPercent = (float) ($discounts[$this->normalizeBrand((string) ($product->brand ?? ''))] ?? 0);
                    $discountPercent = max(0, min(100, $discountPercent));
                    $discountedPrice = round($originalPrice * (1 - ($discountPercent / 100)), 2);

                    return [
                        'id' => (string) $product->id,
                        'cartId' => (int) $cartRow->id,
                        'image' => $this->currentProductImageUrl((int) $product->id),
                        'productCode' => (string) ($product->product_code ?? ''),
                        'partNumber' => (string) ($product->part_number ?? ''),
                        'description' => (string) ($product->description ?? ''),
                        'application' => (string) ($product->application ?? ''),
                        'specification' => (string) ($product->specification ?? ''),
                        'position' => (string) ($product->position ?? ''),
                        'brand' => (string) ($product->brand ?? ''),
                        'price' => $originalPrice,
                        'discountPercent' => $discountPercent,
                        'discountedPrice' => $discountedPrice,
                        'qty' => max(1, min(9999, (int) $cartRow->quantity)),
                        'selected' => (bool) $cartRow->is_selected,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function processedCartIds(int $loginId, int $customerId): Collection
    {
        try {
            if (
                !Schema::connection('sales_order')->hasTable('w68_portal_orders')
                || !Schema::connection('sales_order')->hasTable('w68_portal_order_items')
                || !Schema::connection('sales_order')->hasColumn('w68_portal_order_items', 'cart_id')
            ) {
                return collect();
            }

            return DB::connection('sales_order')
                ->table('w68_portal_order_items as poi')
                ->join('w68_portal_orders as po', 'po.id', '=', 'poi.w68_portal_order_id')
                ->where('po.login_id', $loginId)
                ->where('po.customer_id', $customerId)
                ->whereNotNull('poi.cart_id')
                ->pluck('poi.cart_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();
        } catch (Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    private function currentProductImageUrl(int $productId): string
    {
        $baseUrl = str_replace('\\', '/', (string) request()->getBaseUrl());
        $baseUrl = preg_replace('#/index\\.php$#i', '', rtrim($baseUrl, '/')) ?: '';

        return rtrim(request()->getSchemeAndHttpHost(), '/')
            . $baseUrl
            . '/home/product-image/'
            . $productId;
    }

    /**
     * W68 v107 shipment choices.
     *
     * Customer UI terminology is "Shipment", while the source-of-truth table
     * remains core4_masterlist.forwarders. Only forwarder_type Rush/Regular is
     * exposed to the customer portal; every other type is intentionally hidden.
     */
    private function shipmentForwarders(): Collection
    {
        try {
            if (
                !Schema::connection('masterlist')->hasTable('forwarders')
                || !Schema::connection('masterlist')->hasColumn('forwarders', 'forwarder_type')
            ) {
                return collect();
            }

            return DB::connection('masterlist')
                ->table('forwarders')
                ->whereNotNull('forwarder_type')
                ->whereRaw('LOWER(TRIM(forwarder_type)) IN (?, ?)', ['rush', 'regular'])
                ->orderByRaw("CASE WHEN LOWER(TRIM(forwarder_type)) = 'rush' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'forwarder_type'])
                ->map(function ($row): array {
                    return [
                        'id' => (int) $row->id,
                        'name' => trim((string) ($row->name ?? '')),
                        'type' => mb_strtolower(trim((string) ($row->forwarder_type ?? ''))),
                    ];
                })
                ->filter(fn (array $row): bool => $row['id'] > 0 && $row['name'] !== '' && in_array($row['type'], ['rush', 'regular'], true))
                ->values();
        } catch (Throwable $exception) {
            report($exception);
            return collect();
        }
    }

    private function customerBrandDiscounts(int $customerId): array
    {
        if (!Schema::connection('masterlist')->hasTable('customer_brand_discounts')) {
            return [];
        }

        return DB::connection('masterlist')
            ->table('customer_brand_discounts')
            ->where('customer_id', $customerId)
            ->whereNotNull('brand')
            ->whereRaw("TRIM(brand) <> ''")
            ->get(['brand', 'discount_percentage'])
            ->reduce(function (array $result, $row): array {
                $brand = $this->normalizeBrand((string) ($row->brand ?? ''));
                if ($brand === '') {
                    return $result;
                }
                $discount = max(0, min(100, (float) ($row->discount_percentage ?? 0)));
                $result[$brand] = max($result[$brand] ?? 0, $discount);
                return $result;
            }, []);
    }

    private function identity(Request $request): array
    {
        $account = Auth::user();
        abort_unless($account && (int) ($account->account_type ?? 0) === 5, 403, 'W68 customer access requires account_type = 5.');

        $loginId = (int) (Auth::id() ?? $account->login_ID ?? 0);
        abort_unless($loginId > 0, 422, 'This W68 login is missing a valid login ID.');

        $customerId = (int) $request->session()->get('w68_customer_id', 0);

        if ($customerId <= 0 && Schema::connection('system')->hasTable('customer_portal_accounts')) {
            $customerId = (int) (DB::connection('system')
                ->table('customer_portal_accounts')
                ->where('login_id', $loginId)
                ->value('customer_id') ?? 0);
        }

        if ($customerId <= 0) {
            $userId = trim((string) ($account->User_ID ?? ''));
            if ($userId !== '' && ctype_digit($userId)) {
                $candidate = (int) $userId;
                if (DB::connection('masterlist')->table('customers')->where('id', $candidate)->exists()) {
                    $customerId = $candidate;
                }
            }
        }

        abort_unless($customerId > 0, 422, 'This W68 login is not linked to a valid customer ID.');

        return [$account, $loginId, $customerId];
    }

    private function ensureOrderTables(): void
    {
        abort_unless(
            Schema::connection('sales_order')->hasTable('w68_portal_orders')
            && Schema::connection('sales_order')->hasTable('w68_portal_order_items'),
            503,
            'W68 Orders storage is not installed in core4_sales_order yet.'
        );
    }

    private function ensureCartTable(): void
    {
        abort_unless(
            Schema::connection('sales_order')->hasTable('w68_customer_cart_items'),
            503,
            'W68 cart storage is not installed in core4_sales_order yet.'
        );
    }

    private function normalizeBrand(string $brand): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $brand) ?? ''));
    }

    private function accountDisplayName($account): string
    {
        $name = trim((string) ($account->User_First_Name ?? '') . ' ' . (string) ($account->User_Last_Name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($account->User_ID ?? '')) ?: 'W68 ONLINE';
    }

    private function splitInvoiceNumbers($value): array
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return [];
        }

        if (str_starts_with($text, '[')) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $decoded)));
            }
        }

        return array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            preg_split('/[\r\n,;|]+/', $text) ?: []
        )));
    }
}
