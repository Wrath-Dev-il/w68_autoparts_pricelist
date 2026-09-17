<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class CustomerHomeController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $account = Auth::user();

        if (!$account || (int) ($account->account_type ?? 0) !== 5) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This W68 home page requires an account_type = 5 account.',
                ]);
        }

        $filters = [
            'product_code' => trim((string) $request->query('product_code', '')),
            'part_number' => trim((string) $request->query('part_number', '')),
            'description' => trim((string) $request->query('description', '')),
            'brand' => trim((string) $request->query('brand', '')),
            'application' => trim((string) $request->query('application', '')),
            'position' => trim((string) $request->query('position', '')),
        ];

        $products = collect();
        $newItems = collect();
        $customerId = $this->resolveCustomerId($account, $request);
        $brandDiscounts = $this->customerBrandDiscounts($customerId);
        $portalLoginId = $this->resolvePortalLoginId($account);
        $cartStorageReady = $this->cartTableAvailable();
        $serverCart = $cartStorageReady
            ? $this->customerCartItems($portalLoginId, $customerId, $brandDiscounts)
            : [];

        try {
            $catalog = $this->catalogQuery();
            $this->applyFilters($catalog, $filters);

            // The customer catalog intentionally shows at least 100 products per page.
            $products = $catalog
                ->orderByDesc('p.date_added')
                ->orderByDesc('p.id')
                ->paginate(100)
                ->withQueryString();

            $products->getCollection()->transform(
                fn ($product) => $this->applyBrandDiscount($product, $brandDiscounts)
            );

            // New Items use ONLY products selected for this website.
            $newItems = $this->catalogQuery()
                ->orderByRaw("CASE WHEN p.status = 'Newly' THEN 0 ELSE 1 END")
                ->orderByDesc('p.date_added')
                ->orderByDesc('p.id')
                ->limit(12)
                ->get()
                ->map(fn ($product) => $this->applyBrandDiscount($product, $brandDiscounts));

        } catch (Throwable $exception) {
            report($exception);
        }

        $profileName = trim(
            (string) ($account->User_First_Name ?? '')
            . ' '
            . (string) ($account->User_Last_Name ?? '')
        );

        if ($profileName === '') {
            $profileName = (string) ($account->User_ID ?? 'W68 Customer');
        }

        return view('home', [
            'account' => $account,
            'profileName' => $profileName,
            'products' => $products,
            'newItems' => $newItems,
            'filters' => $filters,
            'brandDiscounts' => $brandDiscounts,
            'hasCustomerDiscounts' => collect($brandDiscounts)
                ->contains(static fn ($discount): bool => (float) $discount > 0),
            'serverCart' => $serverCart,
            'cartAccountKey' => $portalLoginId ? 'login-' . $portalLoginId : 'customer-' . ($customerId ?? 'unknown'),
            'cartStorageReady' => $cartStorageReady,
        ]);
    }

    /**
     * Dedicated customer discount page.
     * Only brands with a positive customer-specific discount are shown.
     */
    public function discounts(Request $request): View|RedirectResponse
    {
        $account = Auth::user();

        if (!$account || (int) ($account->account_type ?? 0) !== 5) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This W68 discounts page requires an account_type = 5 account.',
                ]);
        }

        $customerId = $this->resolveCustomerId($account, $request);
        $brandDiscounts = $this->customerBrandDiscounts($customerId);
        $positiveDiscounts = collect($brandDiscounts)
            ->filter(static fn ($discount): bool => (float) $discount > 0);

        if ($positiveDiscounts->isEmpty()) {
            return redirect()
                ->route('home')
                ->with('status', 'No brand discounts are assigned to this customer account.');
        }

        $brands = collect();

        try {
            $brands = DB::connection('masterlist')
                ->table('products')
                ->where('is_selected_for_report', 1)
                ->whereNotNull('category')
                ->whereRaw("TRIM(category) <> ''")
                ->selectRaw('TRIM(category) as brand')
                ->distinct()
                ->orderBy('brand')
                ->get()
                ->map(function ($row) use ($brandDiscounts): array {
                    $brand = trim((string) ($row->brand ?? ''));
                    $discount = (float) ($brandDiscounts[$this->normalizeBrand($brand)] ?? 0);

                    return [
                        'brand' => $brand,
                        'discount' => $discount,
                    ];
                })
                ->filter(static fn (array $brand): bool => (float) ($brand['discount'] ?? 0) > 0)
                ->values();
        } catch (Throwable $exception) {
            report($exception);
        }

        $profileName = trim(
            (string) ($account->User_First_Name ?? '')
            . ' '
            . (string) ($account->User_Last_Name ?? '')
        );

        if ($profileName === '') {
            $profileName = (string) ($account->User_ID ?? 'W68 Customer');
        }

        $portalLoginId = $this->resolvePortalLoginId($account);
        $cartStorageReady = $this->cartTableAvailable();
        $serverCart = $cartStorageReady
            ? $this->customerCartItems($portalLoginId, $customerId, $brandDiscounts)
            : [];

        return view('discounts', [
            'account' => $account,
            'profileName' => $profileName,
            'brands' => $brands,
            'discountedBrandCount' => $brands->count(),
            'customerId' => $customerId,
            'serverCart' => $serverCart,
        ]);
    }

    /**
     * Live search recommendations for the Home page.
     * Only products explicitly selected for the website are returned.
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        $this->ensureAccountTypeFive();

        $term = trim((string) $request->query('q', ''));
        $field = trim((string) $request->query('field', ''));

        $fieldMap = [
            'product_code' => 'p.product_code',
            'part_number' => 'p.part_number',
            'description' => 'p.description',
            'application' => 'p.application',
            'brand' => 'p.category',
            'position' => 'p.Position',
        ];

        if (mb_strlen($term) < 1 || !isset($fieldMap[$field])) {
            return response()->json(['items' => []]);
        }

        $targetColumn = $fieldMap[$field];
        $escaped = $this->escapeLike($term);
        $contains = '%' . $escaped . '%';
        $starts = $escaped . '%';

        $applyRelatedSearch = static function ($query) use ($contains): void {
            $query->where(function ($nested) use ($contains) {
                $nested
                    ->where('p.product_code', 'like', $contains)
                    ->orWhere('p.part_number', 'like', $contains)
                    ->orWhere('p.description', 'like', $contains)
                    ->orWhere('p.category', 'like', $contains)
                    ->orWhere('p.application', 'like', $contains)
                    ->orWhere('p.Position', 'like', $contains);
            });
        };

        /*
         * Product Code / Part Number identify a concrete product.
         * Show that exact product's selected image.
         *
         * Related matching is intentionally supported:
         * typing "ball" in Product Code can still recommend BALL JOINT rows,
         * but choosing one inserts its REAL product code.
         */
        if (in_array($field, ['product_code', 'part_number'], true)) {
            $query = DB::connection('masterlist')
                ->table('products as p')
                ->where('p.is_selected_for_report', 1)
                ->whereNotNull($targetColumn)
                ->whereRaw("TRIM({$targetColumn}) <> ''");

            $applyRelatedSearch($query);

            $rows = $query
                ->select([
                    'p.id',
                    'p.product_code',
                    'p.part_number',
                    'p.description',
                    'p.category as brand',
                    'p.application',
                    'p.Position as position',
                    'p.selling_price',
                ])
                ->orderByRaw(
                    "CASE
                        WHEN TRIM({$targetColumn}) LIKE ? THEN 0
                        WHEN TRIM({$targetColumn}) LIKE ? THEN 1
                        WHEN p.description LIKE ? THEN 2
                        WHEN p.application LIKE ? THEN 3
                        WHEN p.category LIKE ? THEN 4
                        WHEN p.Position LIKE ? THEN 5
                        ELSE 6
                    END",
                    [
                        $starts,
                        $contains,
                        $contains,
                        $contains,
                        $contains,
                        $contains,
                    ]
                )
                ->orderBy($targetColumn)
                ->limit(12)
                ->get();

            $items = $rows
                ->map(function ($row) use ($field) {
                    $value = $field === 'product_code'
                        ? trim((string) ($row->product_code ?? ''))
                        : trim((string) ($row->part_number ?? ''));

                    if ($value === '') {
                        return null;
                    }

                    return $this->suggestionItem($row, $field, $value);
                })
                ->filter()
                ->values();

            return response()->json(['items' => $items]);
        }

        /*
         * Description / Brand / Application / Position can represent many
         * products. Avoid GROUP BY + correlated RAND() subqueries because
         * MariaDB strict grouping can reject them.
         *
         * Step 1: get up to 12 matching distinct values.
         * Step 2: choose one random selected product for each value.
         */
        $valueQuery = DB::connection('masterlist')
            ->table('products as p')
            ->where('p.is_selected_for_report', 1)
            ->whereNotNull($targetColumn)
            ->whereRaw("TRIM({$targetColumn}) <> ''");

        $applyRelatedSearch($valueQuery);

        $values = $valueQuery
            ->selectRaw("DISTINCT TRIM({$targetColumn}) as recommendation_value")
            ->orderByRaw(
                "CASE
                    WHEN TRIM({$targetColumn}) LIKE ? THEN 0
                    WHEN TRIM({$targetColumn}) LIKE ? THEN 1
                    ELSE 2
                END",
                [$starts, $contains]
            )
            ->orderByRaw("TRIM({$targetColumn})")
            ->limit(12)
            ->pluck('recommendation_value')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $items = collect();

        foreach ($values as $value) {
            $representative = DB::connection('masterlist')
                ->table('products as p')
                ->where('p.is_selected_for_report', 1)
                ->whereRaw("TRIM({$targetColumn}) = ?", [$value])
                ->select([
                    'p.id',
                    'p.product_code',
                    'p.part_number',
                    'p.description',
                    'p.category as brand',
                    'p.application',
                    'p.Position as position',
                    'p.selling_price',
                ])
                ->inRandomOrder()
                ->first();

            if (!$representative) {
                continue;
            }

            $items->push(
                $this->suggestionItem(
                    $representative,
                    $field,
                    $value
                )
            );
        }

        return response()->json([
            'items' => $items->values(),
        ]);
    }

    public function cartAdd(Request $request): JsonResponse
    {
        [$account, $loginId, $customerId] = $this->cartIdentity($request);
        $this->ensureCartTable();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $productId = (int) $validated['product_id'];
        $quantity = (int) $validated['quantity'];

        $productExists = DB::connection('masterlist')
            ->table('products')
            ->where('id', $productId)
            ->where('is_selected_for_report', 1)
            ->exists();

        abort_unless($productExists, 404, 'The selected W68 product is unavailable.');

        $this->purgeProcessedCartRows($loginId, $customerId);

        $cartResult = DB::connection('sales_order')->transaction(function () use ($loginId, $customerId, $productId, $quantity): array {
            $existing = DB::connection('sales_order')
                ->table('w68_customer_cart_items')
                ->where('login_id', $loginId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $now = now();

            if ($existing) {
                $finalQuantity = min(9999, max(1, (int) $existing->quantity + $quantity));

                DB::connection('sales_order')
                    ->table('w68_customer_cart_items')
                    ->where('id', $existing->id)
                    ->update([
                        'customer_id' => $customerId,
                        'quantity' => $finalQuantity,
                        'updated_at' => $now,
                    ]);

                return [
                    'cart_id' => (int) $existing->id,
                    'quantity' => $finalQuantity,
                ];
            }

            $cartId = (int) DB::connection('sales_order')->table('w68_customer_cart_items')->insertGetId([
                'customer_id' => $customerId,
                'login_id' => $loginId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'is_selected' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'cart_id' => $cartId,
                'quantity' => $quantity,
            ];
        });

        return response()->json([
            'ok' => true,
            'product_id' => $productId,
            'cart_id' => $cartResult['cart_id'],
            'quantity' => $cartResult['quantity'],
        ]);
    }

    public function cartState(Request $request): JsonResponse
    {
        [, $loginId, $customerId] = $this->cartIdentity($request);
        $this->ensureCartTable();

        $brandDiscounts = $this->customerBrandDiscounts($customerId);
        $items = $this->customerCartItems($loginId, $customerId, $brandDiscounts);

        return response()->json([
            'ok' => true,
            'login_id' => $loginId,
            'customer_id' => $customerId,
            'items' => $items,
        ]);
    }

    public function cartUpdate(Request $request, int $product): JsonResponse
    {
        [, $loginId, $customerId] = $this->cartIdentity($request);
        $this->ensureCartTable();

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'selected' => ['sometimes', 'boolean'],
        ]);

        abort_if($validated === [], 422, 'No cart changes were supplied.');

        $changes = [
            'customer_id' => $customerId,
            'updated_at' => now(),
        ];

        if (array_key_exists('quantity', $validated)) {
            $changes['quantity'] = (int) $validated['quantity'];
        }

        if (array_key_exists('selected', $validated)) {
            $changes['is_selected'] = $validated['selected'] ? 1 : 0;
        }

        $query = DB::connection('sales_order')
            ->table('w68_customer_cart_items')
            ->where('login_id', $loginId)
            ->where('product_id', $product);

        abort_unless($query->exists(), 404, 'Cart item not found for this account.');

        $query->update($changes);

        return response()->json(['ok' => true]);
    }

    public function cartRemove(Request $request, int $product): JsonResponse
    {
        [, $loginId] = $this->cartIdentity($request);
        $this->ensureCartTable();

        DB::connection('sales_order')
            ->table('w68_customer_cart_items')
            ->where('login_id', $loginId)
            ->where('product_id', $product)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function cartSelection(Request $request): JsonResponse
    {
        [, $loginId] = $this->cartIdentity($request);
        $this->ensureCartTable();

        $validated = $request->validate([
            'selected' => ['required', 'boolean'],
        ]);

        DB::connection('sales_order')
            ->table('w68_customer_cart_items')
            ->where('login_id', $loginId)
            ->update([
                'is_selected' => $validated['selected'] ? 1 : 0,
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    public function cartRemoveSelected(Request $request): JsonResponse
    {
        [, $loginId] = $this->cartIdentity($request);
        $this->ensureCartTable();

        DB::connection('sales_order')
            ->table('w68_customer_cart_items')
            ->where('login_id', $loginId)
            ->where('is_selected', 1)
            ->delete();

        return response()->json(['ok' => true]);
    }


    /**
     * Persist the exact cart currently visible to the logged-in W68 account.
     *
     * This is intentionally used as a final flush before logout and as a
     * recovery path when this browser still has a cart but the server table
     * is empty. It prevents a fast logout/navigation from dropping a queued
     * AJAX cart mutation.
     */
    public function cartSync(Request $request): JsonResponse
    {
        [, $loginId, $customerId] = $this->cartIdentity($request);
        $this->ensureCartTable();

        $validated = $request->validate([
            'items' => ['present', 'array', 'max:500'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.cart_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.selected' => ['sometimes', 'boolean'],
        ]);

        $processedCartIds = $this->processedCartIds($loginId, $customerId);
        $processedLookup = $processedCartIds->flip();

        $items = collect($validated['items'] ?? [])
            ->map(fn (array $item) => [
                'product_id' => (int) $item['product_id'],
                'cart_id' => isset($item['cart_id']) ? (int) $item['cart_id'] : null,
                'quantity' => max(1, min(9999, (int) $item['quantity'])),
                'selected' => !empty($item['selected']),
            ])
            ->filter(fn (array $item) => !$item['cart_id'] || !$processedLookup->has($item['cart_id']))
            ->unique('product_id')
            ->values();

        $validProductIds = $items->isEmpty()
            ? collect()
            : DB::connection('masterlist')
                ->table('products')
                ->where('is_selected_for_report', 1)
                ->whereIn('id', $items->pluck('product_id')->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

        $validLookup = $validProductIds->flip();
        $items = $items
            ->filter(fn (array $item) => $validLookup->has($item['product_id']))
            ->values();

        $this->purgeProcessedCartRows($loginId, $customerId);

        DB::connection('sales_order')->transaction(function () use ($loginId, $customerId, $items): void {
            $incomingProductIds = $items->pluck('product_id')->all();

            $removeQuery = DB::connection('sales_order')
                ->table('w68_customer_cart_items')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId);

            if ($incomingProductIds === []) {
                $removeQuery->delete();
            } else {
                $removeQuery->whereNotIn('product_id', $incomingProductIds)->delete();
            }

            if ($items->isEmpty()) {
                return;
            }

            $now = now();
            foreach ($items as $item) {
                $existing = DB::connection('sales_order')
                    ->table('w68_customer_cart_items')
                    ->where('login_id', $loginId)
                    ->where('product_id', $item['product_id'])
                    ->first(['id']);

                if ($existing) {
                    DB::connection('sales_order')
                        ->table('w68_customer_cart_items')
                        ->where('id', $existing->id)
                        ->update([
                            'customer_id' => $customerId,
                            'quantity' => $item['quantity'],
                            'is_selected' => $item['selected'] ? 1 : 0,
                            'updated_at' => $now,
                        ]);
                    continue;
                }

                DB::connection('sales_order')->table('w68_customer_cart_items')->insert([
                    'customer_id' => $customerId,
                    'login_id' => $loginId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'is_selected' => $item['selected'] ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $brandDiscounts = $this->customerBrandDiscounts($customerId);
        $serverItems = $this->customerCartItems($loginId, $customerId, $brandDiscounts);

        return response()->json([
            'ok' => true,
            'count' => count($serverItems),
            'items' => $serverItems,
        ]);
    }

    private function suggestionItem(
        object $row,
        string $field,
        string $value
    ): array {
        return [
            'id' => (int) $row->id,
            'value' => $value,
            'field' => $field,
            'product_code' => (string) ($row->product_code ?? ''),
            'part_number' => (string) ($row->part_number ?? ''),
            'description' => (string) ($row->description ?? ''),
            'brand' => (string) ($row->brand ?? ''),
            'application' => (string) ($row->application ?? ''),
            'position' => (string) ($row->position ?? ''),
            'price' => number_format(
                (float) ($row->selling_price ?? 0),
                2,
                '.',
                ''
            ),
            'image' => $this->currentProductImageUrl((int) $row->id),
        ];
    }

    /**
     * Build the product-image URL from the CURRENT request host and base path.
     *
     * This matters because W68 runs from:
     * /w68_Pricelist/public
     *
     * A plain relative Laravel route such as /home/product-image/123 can
     * incorrectly point an iPad to:
     * http://192.168.1.20/home/product-image/123
     *
     * The correct LAN URL is:
     * http://192.168.1.20/w68_Pricelist/public/home/product-image/123
     */
    private function currentProductImageUrl(int $productId): string
    {
        $baseUrl = str_replace('\\', '/', (string) request()->getBaseUrl());

        $baseUrl = preg_replace(
            '#/index\.php$#i',
            '',
            rtrim($baseUrl, '/')
        ) ?: '';

        return rtrim(request()->getSchemeAndHttpHost(), '/')
            . $baseUrl
            . '/home/product-image/'
            . $productId;
    }

    /**
     * Serves the cover image selected in Hatdog Product Master.
     * Hatdog moves the chosen cover image to index 0 in Product_Picture,
     * so W68 uses the first valid image from that field.
     */
    public function productImage(int $product)
    {
        $this->ensureAccountTypeFive();

        try {
            $picture = DB::connection('masterlist')
                ->table('products')
                ->where('id', $product)
                ->where('is_selected_for_report', 1)
                ->value('Product_Picture');

            return $this->imageResponse($picture);
        } catch (\Throwable $exception) {
            report($exception);

            /*
             * Product thumbnails must never break the Home page.
             * Return a valid image even when one malformed legacy image
             * exists in Product_Picture.
             */
            return $this->fallbackLogoResponse();
        }
    }

    public function profilePicture()
    {
        $account = $this->ensureAccountTypeFive();

        try {
            $picture = $account->profile_picture ?? null;
            $mime = trim((string) ($account->profile_picture_mime ?? ''));

            if ($picture !== null && $picture !== '') {
                if (
                    is_string($picture)
                    && str_starts_with(trim($picture), 'data:image/')
                ) {
                    return $this->imageResponse($picture);
                }

                if (
                    $mime !== ''
                    && str_starts_with(strtolower($mime), 'image/')
                    && is_string($picture)
                    && $this->looksLikeBinaryImage($picture)
                ) {
                    return response($picture, 200, [
                        'Content-Type' => $mime,
                        'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    ]);
                }

                /*
                 * New W68 profile uploads are stored as a public relative
                 * path in the existing profile_picture column.
                 */
                return $this->imageResponse($picture);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $this->fallbackLogoResponse();
    }

    private function catalogQuery(): Builder
    {
        // IMPORTANT: no online_products table is used on the customer Home page.
        return DB::connection('masterlist')
            ->table('products as p')
            ->where('p.is_selected_for_report', 1)
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
                'p.date_added',
                'p.status',
                'p.created_at',
                'p.updated_at',
            ])
            ->selectRaw('COALESCE(p.selling_price, 0) as display_price');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $columnMap = [
            'product_code' => 'p.product_code',
            'part_number' => 'p.part_number',
            'description' => 'p.description',
            'brand' => 'p.category',
            'application' => 'p.application',
            'position' => 'p.Position',
        ];

        foreach ($columnMap as $filter => $column) {
            if (($filters[$filter] ?? '') === '') {
                continue;
            }

            $query->where(
                $column,
                'like',
                '%' . $this->escapeLike((string) $filters[$filter]) . '%'
            );
        }
    }

    private function distinctValues(string $column)
    {
        return DB::connection('masterlist')
            ->table('products as p')
            ->where('p.is_selected_for_report', 1)
            ->whereNotNull($column)
            ->whereRaw("TRIM({$column}) <> ''")
            ->selectRaw("DISTINCT {$column} as value")
            ->orderBy('value')
            ->pluck('value')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();
    }

    private function resolvePortalLoginId($account): ?int
    {
        $loginId = (int) (Auth::id() ?? $account->login_ID ?? 0);

        return $loginId > 0 ? $loginId : null;
    }

    private function cartIdentity(Request $request): array
    {
        $account = $this->ensureAccountTypeFive();
        $loginId = $this->resolvePortalLoginId($account);
        $customerId = $this->resolveCustomerId($account, $request);

        abort_unless($loginId, 422, 'This W68 login is missing a valid login ID.');
        abort_unless($customerId, 422, 'This W68 login is not linked to a valid customer ID.');

        return [$account, $loginId, $customerId];
    }

    private function cartTableAvailable(): bool
    {
        try {
            return Schema::connection('sales_order')->hasTable('w68_customer_cart_items');
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function ensureCartTable(): void
    {
        abort_unless(
            $this->cartTableAvailable(),
            503,
            'W68 cart storage is not installed in core4_sales_order yet.'
        );
    }

    private function customerCartItems(?int $loginId, ?int $customerId, array $brandDiscounts): array
    {
        if (!$loginId || !$customerId) {
            return [];
        }

        try {
            if (!$this->cartTableAvailable()) {
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
                ->where('p.is_selected_for_report', 1)
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

            return $cartRows
                ->map(function ($cartRow) use ($products, $brandDiscounts) {
                    $product = $products->get($cartRow->product_id);

                    if (!$product) {
                        return null;
                    }

                    $product->display_price = (float) ($product->selling_price ?? 0);
                    $product = $this->applyBrandDiscount($product, $brandDiscounts);

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
                        'price' => (float) ($product->display_price ?? 0),
                        'discountPercent' => (float) ($product->discount_percent ?? 0),
                        'discountedPrice' => (float) ($product->discounted_price ?? $product->display_price ?? 0),
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

    private function processedCartIds(int $loginId, int $customerId)
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

    private function purgeProcessedCartRows(int $loginId, int $customerId): void
    {
        $processedCartIds = $this->processedCartIds($loginId, $customerId);
        if ($processedCartIds->isEmpty()) {
            return;
        }

        DB::connection('sales_order')
            ->table('w68_customer_cart_items')
            ->where('login_id', $loginId)
            ->where('customer_id', $customerId)
            ->whereIn('id', $processedCartIds->all())
            ->delete();
    }

    private function resolveCustomerId($account, Request $request): ?int
    {
        $sessionCustomerId = (int) $request->session()->get('w68_customer_id', 0);

        if ($sessionCustomerId > 0) {
            return $sessionCustomerId;
        }

        $loginId = trim((string) (Auth::id() ?? $account->login_ID ?? ''));

        try {
            if (
                $loginId !== ''
                && Schema::connection('system')->hasTable('customer_portal_accounts')
                && Schema::connection('system')->hasColumn('customer_portal_accounts', 'login_id')
                && Schema::connection('system')->hasColumn('customer_portal_accounts', 'customer_id')
            ) {
                $customerId = DB::connection('system')
                    ->table('customer_portal_accounts')
                    ->where('login_id', $loginId)
                    ->value('customer_id');

                if ($customerId !== null && (int) $customerId > 0) {
                    return (int) $customerId;
                }
            }

            $userId = trim((string) ($account->User_ID ?? ''));

            if (
                $userId !== ''
                && ctype_digit($userId)
                && Schema::connection('masterlist')->hasTable('customers')
            ) {
                $exists = DB::connection('masterlist')
                    ->table('customers')
                    ->where('id', (int) $userId)
                    ->exists();

                if ($exists) {
                    return (int) $userId;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return null;
    }

    private function customerBrandDiscounts(?int $customerId): array
    {
        if (!$customerId) {
            return [];
        }

        try {
            if (
                !Schema::connection('masterlist')->hasTable('customer_brand_discounts')
                || !Schema::connection('masterlist')->hasColumn('customer_brand_discounts', 'customer_id')
                || !Schema::connection('masterlist')->hasColumn('customer_brand_discounts', 'brand')
                || !Schema::connection('masterlist')->hasColumn('customer_brand_discounts', 'discount_percentage')
            ) {
                return [];
            }

            return DB::connection('masterlist')
                ->table('customer_brand_discounts')
                ->where('customer_id', $customerId)
                ->whereNotNull('brand')
                ->whereRaw("TRIM(brand) <> ''")
                ->select(['brand', 'discount_percentage'])
                ->get()
                ->reduce(function (array $discounts, $row): array {
                    $brand = $this->normalizeBrand((string) ($row->brand ?? ''));

                    if ($brand === '') {
                        return $discounts;
                    }

                    $discount = max(
                        0,
                        min(100, (float) ($row->discount_percentage ?? 0))
                    );

                    $discounts[$brand] = max($discounts[$brand] ?? 0, $discount);

                    return $discounts;
                }, []);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function applyBrandDiscount($product, array $brandDiscounts)
    {
        $price = (float) ($product->display_price ?? $product->selling_price ?? 0);
        $discountPercent = $brandDiscounts[$this->normalizeBrand((string) ($product->brand ?? ''))] ?? 0;
        $hasDiscount = $price > 0 && $discountPercent > 0;

        $product->discount_percent = $hasDiscount ? $discountPercent : 0;
        $product->discounted_price = $hasDiscount
            ? round($price - ($price * ($discountPercent / 100)), 2)
            : $price;
        $product->has_discount = $hasDiscount;

        return $product;
    }

    private function normalizeBrand(string $brand): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $brand) ?? ''));
    }

    private function ensureAccountTypeFive()
    {
        $account = Auth::user();

        abort_unless(
            $account && (int) ($account->account_type ?? 0) === 5,
            403,
            'W68 customer access requires account_type = 5.'
        );

        return $account;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }

    private function imageResponse($value)
    {
        try {
            if ($value === null || $value === '') {
                return $this->fallbackLogoResponse();
            }

            if (is_resource($value)) {
                $contents = stream_get_contents($value);
                $value = $contents === false ? null : $contents;
            }

            if (is_array($value)) {
                $selected = $this->firstImageFromDecodedValue($value);

                return $selected !== null
                    ? $this->imageResponse($selected)
                    : $this->fallbackLogoResponse();
            }

            if (!is_string($value)) {
                if ($value instanceof \Stringable) {
                    $value = (string) $value;
                } else {
                    return $this->fallbackLogoResponse();
                }
            }

            if ($value === '') {
                return $this->fallbackLogoResponse();
            }

            /*
             * Raw image binary stored directly in BLOB.
             */
            if ($this->looksLikeBinaryImage($value)) {
                return response($value, 200, [
                    'Content-Type' => $this->detectImageMime($value),
                    'Cache-Control' => 'private, max-age=3600',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            $trimmed = trim($value);

            if ($trimmed === '') {
                return $this->fallbackLogoResponse();
            }

            /*
             * Hatdog Product Master stores Product_Picture as JSON:
             * ["data:image/jpeg;base64,...", "..."]
             * with the selected catalog cover moved to index 0.
             */
            if (
                str_starts_with($trimmed, '[')
                || str_starts_with($trimmed, '{')
                || str_starts_with($trimmed, '"')
            ) {
                $decoded = json_decode($trimmed, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $selected = $this->firstImageFromDecodedValue($decoded);

                    if ($selected !== null) {
                        return $this->imageResponse($selected);
                    }

                    return $this->fallbackLogoResponse();
                }
            }

            /*
             * Handle old serialized PHP arrays if any legacy record exists.
             */
            if (
                preg_match('/^(a|s|O|C):\d+:/', $trimmed) === 1
            ) {
                $decoded = @unserialize(
                    $trimmed,
                    ['allowed_classes' => false]
                );

                if ($decoded !== false || $trimmed === 'b:0;') {
                    $selected = $this->firstImageFromDecodedValue($decoded);

                    if ($selected !== null) {
                        return $this->imageResponse($selected);
                    }
                }
            }

            /*
             * data:image/...;base64,...
             */
            if (
                preg_match(
                    '#^data:(image/[a-z0-9.+-]+);base64,(.+)$#is',
                    $trimmed,
                    $matches
                )
            ) {
                $encoded = preg_replace('/\s+/', '', $matches[2]);
                $binary = base64_decode($encoded, true);

                if (
                    $binary !== false
                    && $binary !== ''
                    && $this->looksLikeBinaryImage($binary)
                ) {
                    return response($binary, 200, [
                        'Content-Type' => strtolower($matches[1]),
                        'Cache-Control' => 'private, max-age=3600',
                        'X-Content-Type-Options' => 'nosniff',
                    ]);
                }

                return $this->fallbackLogoResponse();
            }

            /*
             * Remote URL stored by Product Master.
             */
            if (
                str_starts_with($trimmed, 'http://')
                || str_starts_with($trimmed, 'https://')
            ) {
                $url = $trimmed;
                $parts = parse_url($trimmed);
                $host = strtolower((string) ($parts['host'] ?? ''));

                /*
                 * Hatdog may have saved localhost in the image URL.
                 * Replace only the host so LAN/iPad users use the current
                 * W68 server IP instead of their own device's localhost.
                 */
                if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                    $url = request()->getSchemeAndHttpHost()
                        . (string) ($parts['path'] ?? '');

                    if (!empty($parts['query'])) {
                        $url .= '?' . $parts['query'];
                    }
                }

                return redirect()->away($url);
            }

            /*
             * Relative public file path. New profile pictures use this path.
             */
            $relative = ltrim(
                str_replace('\\', '/', $trimmed),
                '/'
            );

            if (
                $relative !== ''
                && !str_contains($relative, '../')
                && !str_contains($relative, '..\\')
            ) {
                $localPath = public_path($relative);

                if (is_file($localPath)) {
                    return response()->file($localPath, [
                        'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                        'X-Content-Type-Options' => 'nosniff',
                    ]);
                }
            }

            /*
             * Some very old records contain bare base64 without data:image.
             */
            if (
                strlen($trimmed) > 100
                && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $trimmed) === 1
            ) {
                $binary = base64_decode(
                    preg_replace('/\s+/', '', $trimmed),
                    true
                );

                if (
                    $binary !== false
                    && $this->looksLikeBinaryImage($binary)
                ) {
                    return response($binary, 200, [
                        'Content-Type' => $this->detectImageMime($binary),
                        'Cache-Control' => 'private, max-age=3600',
                        'X-Content-Type-Options' => 'nosniff',
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $this->fallbackLogoResponse();
    }

    private function firstImageFromDecodedValue($decoded): ?string
    {
        if (is_string($decoded) && trim($decoded) !== '') {
            return $decoded;
        }

        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_array($value)) {
                foreach (
                    ['url', 'src', 'image', 'image_url', 'path'] as $key
                ) {
                    if (
                        !empty($value[$key])
                        && is_string($value[$key])
                    ) {
                        return $value[$key];
                    }
                }

                $nested = $this->firstImageFromDecodedValue($value);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function looksLikeBinaryImage(string $value): bool
    {
        $length = strlen($value);

        if (
            $length >= 8
            && substr($value, 0, 8) === "\x89PNG\r\n\x1a\n"
        ) {
            return true;
        }

        if (
            $length >= 3
            && substr($value, 0, 3) === "\xFF\xD8\xFF"
        ) {
            return true;
        }

        if (
            $length >= 12
            && substr($value, 0, 4) === 'RIFF'
            && substr($value, 8, 4) === 'WEBP'
        ) {
            return true;
        }

        if (
            $length >= 6
            && (
                substr($value, 0, 6) === 'GIF87a'
                || substr($value, 0, 6) === 'GIF89a'
            )
        ) {
            return true;
        }

        return false;
    }

    private function detectImageMime(string $binary): string
    {
        if (
            strlen($binary) >= 8
            && substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n"
        ) {
            return 'image/png';
        }

        if (
            strlen($binary) >= 3
            && substr($binary, 0, 3) === "\xFF\xD8\xFF"
        ) {
            return 'image/jpeg';
        }

        if (
            strlen($binary) >= 12
            && substr($binary, 0, 4) === 'RIFF'
            && substr($binary, 8, 4) === 'WEBP'
        ) {
            return 'image/webp';
        }

        if (
            str_starts_with($binary, 'GIF87a')
            || str_starts_with($binary, 'GIF89a')
        ) {
            return 'image/gif';
        }

        if (class_exists(\finfo::class)) {
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($binary);

                if (
                    is_string($mime)
                    && str_starts_with($mime, 'image/')
                ) {
                    return $mime;
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return 'application/octet-stream';
    }

    private function fallbackLogoResponse()
    {
        /*
         * Prefer the existing W68 project logo when available.
         */
        $candidates = [
            public_path('build/assets/images/sidebar_logo.png'),
            public_path('images/login/logo.png'),
            public_path('images/logo.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return response()->file($path, [
                    'Cache-Control' => 'private, max-age=3600',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        /*
         * Last-resort inline W68 SVG. This intentionally returns HTTP 200 so
         * an empty/corrupt legacy Product_Picture never creates a broken
         * recommendation/card image or a console 500.
         */
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320">
  <rect width="320" height="320" rx="28" fill="#ffffff"/>
  <circle cx="160" cy="160" r="112" fill="#ffd633" stroke="#5f0b1c" stroke-width="14"/>
  <text x="160" y="174" text-anchor="middle" font-family="Arial,Helvetica,sans-serif"
        font-size="62" font-weight="900" fill="#5f0b1c">W68</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
