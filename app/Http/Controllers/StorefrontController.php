<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $brand = trim((string) $request->query('brand', $request->query('category', '')));
        $description = trim((string) $request->query('description', ''));
        $descriptionSearch = trim((string) $request->query('description_search', ''));
        $brandSearch = trim((string) $request->query('brand_search', ''));
        $productCodeSearch = trim((string) $request->query('product_code', ''));
        $productId = max(0, (int) $request->query('product_id', 0));
        $applicationSearch = trim((string) $request->query('application', ''));
        $partNumberSearch = trim((string) $request->query('part_number', ''));
        $positionSearch = trim((string) $request->query('position', ''));
        $sort = (string) $request->query('sort', 'latest');

        $products = collect();
        $brands = collect();
        $descriptions = collect();
        $newItems = collect();
        $topCategorySellers = collect();
        $fastLookup = null;
        $stats = [
            'products' => 0,
            'brands' => 0,
            'car_brands' => 0,
        ];

        try {
            $connection = DB::connection('masterlist');

            $catalogIndex = $connection->table('online_products as op')
                ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
                ->where('op.is_converted', 1)
                ->select([
                    'op.id',
                    'op.product_id',
                    'op.product_code as online_product_code',
                    'op.name',
                    'op.image_url',
                    'p.product_code as master_product_code',
                    'p.part_number',
                    'p.specification',
                    'p.category as master_category',
                    'p.description as master_description',
                    'p.application',
                    'p.Position as position',
                    'p.on_hand',
                ])
                ->selectRaw("COALESCE(NULLIF(p.product_code, ''), NULLIF(op.product_code, ''), '') as display_product_code")
                ->selectRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') as display_category")
                ->selectRaw("COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), 'Other') as display_description")
                ->get();

            $catalogProductIds = $catalogIndex
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            $salesByProduct = $this->qualifyingSalesByProduct($catalogProductIds);

            $brands = $catalogIndex
                ->groupBy('display_category')
                ->map(function (Collection $items, string $name) use ($salesByProduct) {
                    $ranked = $items
                        ->map(function ($item) use ($salesByProduct) {
                            $item->sold_qty = (int) ($salesByProduct[$item->product_id] ?? 0);
                            return $item;
                        })
                        ->sortByDesc('sold_qty')
                        ->values();

                    $topSeller = $ranked->first();
                    if (!$topSeller || (int) $topSeller->sold_qty <= 0) {
                        $topSeller = null;
                    }

                    return (object) [
                        'name' => $name,
                        'total' => $items->count(),
                        'top_seller' => $topSeller,
                    ];
                })
                ->sortByDesc('total')
                ->values();

            $topCategorySellers = $brands
                ->filter(fn ($item) => $item->top_seller !== null)
                ->map(function ($item) {
                    return (object) [
                        'category' => $item->name,
                        'product' => $item->top_seller,
                    ];
                })
                ->sortByDesc(fn ($item) => (int) ($item->product->sold_qty ?? 0))
                ->values();

            if ($brand !== '') {
                $topCategorySellers = $topCategorySellers
                    ->sortByDesc(fn ($item) => $item->category === $brand ? 1 : 0)
                    ->values();
            }

            $descriptions = $catalogIndex
                ->filter(fn ($item) => trim((string) $item->display_description) !== '')
                ->groupBy('display_description')
                ->map(function (Collection $items, string $name) {
                    return (object) [
                        'name' => $name,
                        'total' => $items->count(),
                    ];
                })
                ->sortByDesc('total')
                ->values();

            $stats['products'] = $catalogIndex->count();
            $stats['brands'] = $brands->count();
            $stats['car_brands'] = $descriptions->count();

            $catalog = $this->catalogQuery();
            $this->applyFilters(
                $catalog,
                $search,
                $brand,
                $description,
                $descriptionSearch,
                $brandSearch,
                $productCodeSearch,
                $productId,
                $applicationSearch,
                $partNumberSearch,
                $positionSearch
            );
            $this->applySort($catalog, $sort);

            $products = $catalog->paginate(30)->withQueryString();

            $yearStart = now()->startOfYear()->toDateTimeString();
            $yearEnd = now()->endOfYear()->toDateTimeString();

            $newItems = $this->catalogQuery()
                ->where(function (Builder $query) use ($yearStart, $yearEnd) {
                    $query->whereBetween('p.created_at', [$yearStart, $yearEnd])
                        ->orWhereBetween('op.created_at', [$yearStart, $yearEnd]);
                })
                ->orderByRaw('COALESCE(p.created_at, op.created_at) DESC')
                ->get();

            $fastLookup = $this->catalogQuery()
                ->orderByDesc('p.on_hand')
                ->orderByDesc('op.updated_at')
                ->first();
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('storefront', compact(
            'products',
            'brands',
            'descriptions',
            'newItems',
            'topCategorySellers',
            'fastLookup',
            'stats',
            'search',
            'brand',
            'description',
            'descriptionSearch',
            'brandSearch',
            'productCodeSearch',
            'productId',
            'applicationSearch',
            'partNumberSearch',
            'positionSearch',
            'sort'
        ));
    }

    public function searchSuggestions(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 1) {
            return response()->json([]);
        }

        try {
            $like = '%' . $search . '%';

            $items = $this->catalogQuery()
                ->where(function (Builder $query) use ($like) {
                    $query->where('op.name', 'like', $like)
                        ->orWhere('p.product_code', 'like', $like)
                        ->orWhere('op.product_code', 'like', $like)
                        ->orWhere('op.sku', 'like', $like)
                        ->orWhere('op.description', 'like', $like)
                        ->orWhere('p.part_number', 'like', $like)
                        ->orWhere('p.application', 'like', $like)
                        ->orWhere('p.Position', 'like', $like)
                        ->orWhere('p.description', 'like', $like)
                        ->orWhere('p.category', 'like', $like)
                        ->orWhere('p.specification', 'like', $like);
                })
                ->orderByRaw(
                    "CASE
                        WHEN p.product_code = ? THEN 0
                        WHEN op.product_code = ? THEN 1
                        WHEN p.part_number = ? THEN 2
                        WHEN p.product_code LIKE ? THEN 3
                        WHEN op.product_code LIKE ? THEN 4
                        WHEN p.part_number LIKE ? THEN 5
                        WHEN op.name LIKE ? THEN 6
                        ELSE 7
                    END",
                    [$search, $search, $search, $search . '%', $search . '%', $search . '%', $search . '%']
                )
                ->limit(8)
                ->get();

            return response()->json(
                $items->map(function ($item) {
                    return [
                        'id' => (int) $item->id,
                        'product_code' => (string) ($item->display_product_code ?? ''),
                        'name' => (string) $item->name,
                        'description' => (string) ($item->display_description ?: $item->name),
                        'part_number' => (string) ($item->part_number ?? ''),
                        'brand' => (string) ($item->display_category ?? ''),
                        'application' => (string) ($item->application ?? ''),
                        'position' => (string) ($item->position ?? ''),
                        'specification' => (string) ($item->specification ?? ''),
                        'price' => (float) $item->display_price,
                        'image_url' => (string) ($item->image_url ?? ''),
                        'url' => route('storefront', ['product_code' => $item->display_product_code]) . '#products',
                    ];
                })->values()
            );
        } catch (Throwable $exception) {
            report($exception);
            return response()->json([]);
        }
    }

    private function catalogQuery(): Builder
    {
        return DB::connection('masterlist')
            ->table('online_products as op')
            ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
            ->where('op.is_converted', 1)
            ->select([
                'op.id',
                'op.product_id',
                'op.product_code as online_product_code',
                'op.name',
                'op.description as online_description',
                'op.sku',
                'op.price',
                'op.image_url',
                'op.category as online_category',
                'op.created_at as online_created_at',
                'op.updated_at',
                'p.product_code as master_product_code',
                'p.part_number',
                'p.specification',
                'p.category as master_category',
                'p.description as master_description',
                'p.application',
                'p.Position as position',
                'p.on_hand',
                'p.status',
                'p.selling_price',
                'p.price_online',
                'p.created_at as product_created_at',
                'p.date_added',
            ])
            ->selectRaw("COALESCE(NULLIF(p.product_code, ''), NULLIF(op.product_code, ''), '') as display_product_code")
            ->selectRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') as display_category")
            ->selectRaw("COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), 'Other') as display_description")
            ->selectRaw('COALESCE(NULLIF(op.price, 0), NULLIF(p.price_online, 0), p.selling_price, 0) as display_price');
    }

    private function qualifyingSalesByProduct(Collection $productIds): Collection
    {
        if ($productIds->isEmpty()) {
            return collect();
        }

        try {
            return DB::connection('ledger')
                ->table('product_ledgers')
                ->whereIn('product_id', $productIds->all())
                ->where('transaction_type', 'OUT')
                ->where('quantity_out', '>', 0)
                ->where(function (Builder $query) {
                    $query->whereRaw("LOWER(COALESCE(remarks, '')) LIKE ?", ['%online report generation%'])
                        ->orWhereRaw("LOWER(COALESCE(remarks, '')) LIKE ?", ['%chginv%'])
                        ->orWhereRaw("LOWER(COALESCE(remarks, '')) LIKE ?", ['%sales order%']);
                })
                ->whereRaw("LOWER(COALESCE(remarks, '')) NOT LIKE ?", ['%sales return%'])
                ->whereRaw("LOWER(COALESCE(remarks, '')) NOT LIKE ?", ['%adjust%'])
                ->whereRaw("LOWER(COALESCE(remarks, '')) NOT LIKE ?", ['%purchase return%'])
                ->groupBy('product_id')
                ->selectRaw('product_id, SUM(quantity_out) as sold_qty')
                ->pluck('sold_qty', 'product_id');
        } catch (Throwable $exception) {
            report($exception);
            return collect();
        }
    }

    private function applyFilters(
        Builder $query,
        string $search,
        string $brand,
        string $description,
        string $descriptionSearch,
        string $brandSearch,
        string $productCodeSearch,
        int $productId,
        string $applicationSearch,
        string $partNumberSearch,
        string $positionSearch
    ): void {
        if ($search !== '') {
            $query->where(function (Builder $filter) use ($search) {
                $like = '%' . $search . '%';

                $filter->where('op.name', 'like', $like)
                    ->orWhere('p.product_code', 'like', $like)
                    ->orWhere('op.product_code', 'like', $like)
                    ->orWhere('op.sku', 'like', $like)
                    ->orWhere('op.description', 'like', $like)
                    ->orWhere('p.part_number', 'like', $like)
                    ->orWhere('p.application', 'like', $like)
                    ->orWhere('p.Position', 'like', $like)
                    ->orWhere('p.description', 'like', $like)
                    ->orWhere('p.category', 'like', $like)
                    ->orWhere('p.specification', 'like', $like);
            });
        }

        if ($brand !== '') {
            $query->whereRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') = ?", [$brand]);
        }

        if ($description !== '') {
            $query->whereRaw("COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), 'Other') = ?", [$description]);
        }

        if ($descriptionSearch !== '') {
            $query->whereRaw(
                "COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), 'Other') LIKE ?",
                ['%' . $descriptionSearch . '%']
            );
        }

        if ($brandSearch !== '') {
            $query->whereRaw(
                "COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') LIKE ?",
                ['%' . $brandSearch . '%']
            );
        }

        if ($productCodeSearch !== '') {
            $query->whereRaw(
                "COALESCE(NULLIF(p.product_code, ''), NULLIF(op.product_code, ''), '') LIKE ?",
                ['%' . $productCodeSearch . '%']
            );
        }

        if ($productId > 0) {
            $query->where('op.id', $productId);
        }

        if ($applicationSearch !== '') {
            $query->where('p.application', 'like', '%' . $applicationSearch . '%');
        }

        if ($partNumberSearch !== '') {
            $query->where('p.part_number', 'like', '%' . $partNumberSearch . '%');
        }

        if ($positionSearch !== '') {
            $query->where('p.Position', 'like', '%' . $positionSearch . '%');
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_low' => $query->orderByRaw('COALESCE(NULLIF(op.price, 0), NULLIF(p.price_online, 0), p.selling_price, 0) ASC'),
            'price_high' => $query->orderByRaw('COALESCE(NULLIF(op.price, 0), NULLIF(p.price_online, 0), p.selling_price, 0) DESC'),
            default => $query->orderByRaw('COALESCE(op.updated_at, p.updated_at, op.created_at, p.created_at) DESC'),
        };
    }
}
