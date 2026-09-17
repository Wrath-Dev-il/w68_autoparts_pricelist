<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $sort = (string) $request->query('sort', 'latest');

        $products = collect();
        $categories = collect();
        $featured = collect();
        $stats = [
            'products' => 0,
            'in_stock' => 0,
            'categories' => 0,
        ];
        $dbError = null;

        try {
            $connection = DB::connection('masterlist');

            $catalog = $connection->table('online_products as op')
                ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
                ->where('op.is_converted', 1)
                ->select([
                    'op.id',
                    'op.product_id',
                    'op.product_code',
                    'op.name',
                    'op.description',
                    'op.sku',
                    'op.price',
                    'op.image_url',
                    'op.category as online_category',
                    'op.updated_at',
                    'p.part_number',
                    'p.category as master_category',
                    'p.application',
                    'p.Position as position',
                    'p.on_hand',
                    'p.status',
                    'p.selling_price',
                    'p.price_online',
                ])
                ->selectRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') as display_category")
                ->selectRaw('COALESCE(NULLIF(op.price, 0), NULLIF(p.price_online, 0), p.selling_price, 0) as display_price');

            $this->applyFilters($catalog, $search, $category);
            $this->applySort($catalog, $sort);

            $products = $catalog->paginate(30)->withQueryString();

            $categories = $connection->table('online_products as op')
                ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
                ->where('op.is_converted', 1)
                ->selectRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') as name")
                ->selectRaw('COUNT(*) as total')
                ->groupByRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other')")
                ->orderByDesc('total')
                ->limit(14)
                ->get();

            $featured = $connection->table('online_products as op')
                ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
                ->where('op.is_converted', 1)
                ->where(function (Builder $query) {
                    $query->whereNull('p.on_hand')->orWhere('p.on_hand', '>', 0);
                })
                ->select([
                    'op.id',
                    'op.product_code',
                    'op.name',
                    'op.image_url',
                    'p.part_number',
                    'p.on_hand',
                ])
                ->selectRaw('COALESCE(NULLIF(op.price, 0), NULLIF(p.price_online, 0), p.selling_price, 0) as display_price')
                ->orderByDesc('op.updated_at')
                ->limit(6)
                ->get();

            $stats['products'] = (int) $connection->table('online_products')->where('is_converted', 1)->count();
            $stats['in_stock'] = (int) $connection->table('online_products as op')
                ->join('products as p', 'p.id', '=', 'op.product_id')
                ->where('op.is_converted', 1)
                ->where('p.on_hand', '>', 0)
                ->count();
            $stats['categories'] = (int) $connection->table('online_products as op')
                ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
                ->where('op.is_converted', 1)
                ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other')) as total")
                ->value('total');
        } catch (Throwable $exception) {
            report($exception);
            $dbError = 'The storefront is ready, but the core4_masterlist database connection is not available yet.';
        }

        return view('storefront', compact(
            'products',
            'categories',
            'featured',
            'stats',
            'search',
            'category',
            'sort',
            'dbError'
        ));
    }

    private function applyFilters(Builder $query, string $search, string $category): void
    {
        if ($search !== '') {
            $query->where(function (Builder $filter) use ($search) {
                $like = '%' . $search . '%';

                $filter->where('op.name', 'like', $like)
                    ->orWhere('op.product_code', 'like', $like)
                    ->orWhere('op.sku', 'like', $like)
                    ->orWhere('op.description', 'like', $like)
                    ->orWhere('p.part_number', 'like', $like)
                    ->orWhere('p.application', 'like', $like)
                    ->orWhere('p.category', 'like', $like);
            });
        }

        if ($category !== '') {
            $query->whereRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') = ?", [$category]);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_low' => $query->orderByRaw('COALESCE(NULLIF(op.price, 0), NULLIF(p.price_online, 0), p.selling_price, 0) ASC'),
            'price_high' => $query->orderByRaw('COALESCE(NULLIF(op.price, 0), NULLIF(p.price_online, 0), p.selling_price, 0) DESC'),
            'stock' => $query->orderByDesc('p.on_hand')->orderByDesc('op.updated_at'),
            default => $query->orderByDesc('op.updated_at'),
        };
    }
}
