<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $filterOptions = [
            'brands' => collect(),
            'descriptions' => collect(),
            'positions' => collect(),
            'applications' => collect(),
        ];

        try {
            $catalog = $this->catalogQuery();
            $this->applyFilters($catalog, $filters);

            $products = $catalog
                ->orderByRaw('COALESCE(op.updated_at, p.updated_at, op.created_at, p.created_at) DESC')
                ->paginate(30)
                ->withQueryString();

            $newItems = $this->catalogQuery()
                ->orderByRaw('COALESCE(p.created_at, op.created_at) DESC')
                ->limit(12)
                ->get();

            $filterOptions = [
                'brands' => $this->distinctValues(
                    "COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), '')"
                ),
                'descriptions' => $this->distinctValues(
                    "COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), '')"
                ),
                'positions' => $this->distinctValues("COALESCE(NULLIF(p.Position, ''), '')"),
                'applications' => $this->distinctValues("COALESCE(NULLIF(p.application, ''), '')"),
            ];
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('home', [
            'account' => $account,
            'products' => $products,
            'newItems' => $newItems,
            'filterOptions' => $filterOptions,
            'filters' => $filters,
        ]);
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
                'op.name',
                'op.image_url',
                'op.created_at as online_created_at',
                'op.updated_at as online_updated_at',
                'p.product_code',
                'p.part_number',
                'p.description',
                'p.category as brand',
                'p.application',
                'p.specification',
                'p.Position as position',
                'p.selling_price',
                'p.created_at as product_created_at',
                'p.updated_at as product_updated_at',
            ])
            ->selectRaw(
                "COALESCE(NULLIF(p.product_code, ''), NULLIF(op.product_code, ''), '') as display_product_code"
            )
            ->selectRaw(
                "COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), op.name, '') as display_description"
            )
            ->selectRaw(
                "COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), '') as display_brand"
            )
            // W68 customer Home uses selling_price only.
            ->selectRaw('COALESCE(p.selling_price, 0) as display_price');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['product_code'] !== '') {
            $query->whereRaw(
                "COALESCE(NULLIF(p.product_code, ''), NULLIF(op.product_code, ''), '') LIKE ?",
                ['%' . $filters['product_code'] . '%']
            );
        }

        if ($filters['part_number'] !== '') {
            $query->where('p.part_number', 'like', '%' . $filters['part_number'] . '%');
        }

        if ($filters['description'] !== '') {
            $query->whereRaw(
                "COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), op.name, '') LIKE ?",
                ['%' . $filters['description'] . '%']
            );
        }

        if ($filters['brand'] !== '') {
            $query->whereRaw(
                "COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), '') LIKE ?",
                ['%' . $filters['brand'] . '%']
            );
        }

        if ($filters['application'] !== '') {
            $query->where('p.application', 'like', '%' . $filters['application'] . '%');
        }

        if ($filters['position'] !== '') {
            $query->where('p.Position', 'like', '%' . $filters['position'] . '%');
        }
    }

    private function distinctValues(string $expression)
    {
        return DB::connection('masterlist')
            ->table('online_products as op')
            ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
            ->where('op.is_converted', 1)
            ->whereRaw("{$expression} <> ''")
            ->selectRaw("DISTINCT {$expression} as value")
            ->orderBy('value')
            ->pluck('value')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values();
    }
}
