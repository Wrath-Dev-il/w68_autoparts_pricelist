<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $brand = trim((string) $request->query('brand', $request->query('category', '')));
        $description = trim((string) $request->query('description', ''));
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
                    'op.product_code',
                    'op.name',
                    'op.image_url',
                    'p.part_number',
                    'p.category as master_category',
                    'p.description as master_description',
                    'p.application',
                    'p.Position as position',
                    'p.on_hand',
                ])
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
            'applicationSearch',
            'partNumberSearch',
            'positionSearch',
            'sort'
        ));
    }

    public function transparentImage(int $id)
    {
        try {
            $imageUrl = (string) DB::connection('masterlist')
                ->table('online_products')
                ->where('id', $id)
                ->value('image_url');

            if ($imageUrl === '') {
                abort(404);
            }

            $cacheDirectory = storage_path('app/w68-transparent-images');
            if (!is_dir($cacheDirectory)) {
                @mkdir($cacheDirectory, 0777, true);
            }

            $cacheFile = $cacheDirectory . DIRECTORY_SEPARATOR
                . 'online_' . $id . '_' . sha1($imageUrl) . '.png';

            if (is_file($cacheFile)) {
                return response()->file($cacheFile, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=604800, immutable',
                ]);
            }

            $imageBytes = null;

            if (preg_match('/^https?:\/\//i', $imageUrl)) {
                $response = Http::withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/153 Safari/537.36',
                        'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    ])
                    ->withOptions(['verify' => false])
                    ->timeout(20)
                    ->retry(1, 150)
                    ->get($imageUrl);

                if ($response->successful()) {
                    $imageBytes = $response->body();
                }
            } else {
                $localPath = public_path(ltrim(str_replace('\\', '/', $imageUrl), '/'));
                if (is_file($localPath)) {
                    $imageBytes = file_get_contents($localPath);
                }
            }

            if (!$imageBytes || !function_exists('imagecreatefromstring')) {
                return redirect()->away($imageUrl);
            }

            $source = @imagecreatefromstring($imageBytes);
            if (!$source) {
                return redirect()->away($imageUrl);
            }

            $width = imagesx($source);
            $height = imagesy($source);

            // Keep hero processing lightweight even when the source image is very large.
            $maxDimension = 900;
            if (max($width, $height) > $maxDimension) {
                $scale = $maxDimension / max($width, $height);
                $newWidth = max(1, (int) round($width * $scale));
                $newHeight = max(1, (int) round($height * $scale));

                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);

                imagecopyresampled(
                    $resized,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $newWidth,
                    $newHeight,
                    $width,
                    $height
                );

                imagedestroy($source);
                $source = $resized;
                $width = $newWidth;
                $height = $newHeight;
            }

            $output = imagecreatetruecolor($width, $height);
            imagealphablending($output, false);
            imagesavealpha($output, true);

            $fullyTransparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
            imagefill($output, 0, 0, $fullyTransparent);

            // Remove only near-white / neutral background pixels.
            // Colored packaging, labels and the product itself remain untouched.
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rgba = imagecolorat($source, $x, $y);

                    $alpha = ($rgba >> 24) & 0x7F;
                    $red = ($rgba >> 16) & 0xFF;
                    $green = ($rgba >> 8) & 0xFF;
                    $blue = $rgba & 0xFF;

                    $maxChannel = max($red, $green, $blue);
                    $minChannel = min($red, $green, $blue);
                    $spread = $maxChannel - $minChannel;
                    $brightness = ($red + $green + $blue) / 3;

                    $newAlpha = $alpha;

                    if ($brightness >= 246 && $spread <= 20) {
                        $newAlpha = 127;
                    } elseif ($brightness >= 232 && $spread <= 16) {
                        // Feather compressed/off-white edges instead of leaving a white halo.
                        $feather = (int) round((($brightness - 232) / 14) * 127);
                        $newAlpha = max($alpha, min(127, $feather));
                    }

                    $color = imagecolorallocatealpha(
                        $output,
                        $red,
                        $green,
                        $blue,
                        $newAlpha
                    );

                    imagesetpixel($output, $x, $y, $color);
                }
            }

            @imagepng($output, $cacheFile, 7);

            imagedestroy($output);
            imagedestroy($source);

            if (is_file($cacheFile)) {
                return response()->file($cacheFile, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=604800, immutable',
                ]);
            }

            return redirect()->away($imageUrl);
        } catch (Throwable $exception) {
            report($exception);

            try {
                $imageUrl = (string) DB::connection('masterlist')
                    ->table('online_products')
                    ->where('id', $id)
                    ->value('image_url');

                if ($imageUrl !== '') {
                    return redirect()->away($imageUrl);
                }
            } catch (Throwable $ignored) {
                // Fall through to 404.
            }

            abort(404);
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
                'op.product_code',
                'op.name',
                'op.description as online_description',
                'op.sku',
                'op.price',
                'op.image_url',
                'op.category as online_category',
                'op.created_at as online_created_at',
                'op.updated_at',
                'p.part_number',
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
        string $applicationSearch,
        string $partNumberSearch,
        string $positionSearch
    ): void {
        if ($search !== '') {
            $query->where(function (Builder $filter) use ($search) {
                $like = '%' . $search . '%';

                $filter->where('op.name', 'like', $like)
                    ->orWhere('op.product_code', 'like', $like)
                    ->orWhere('op.sku', 'like', $like)
                    ->orWhere('op.description', 'like', $like)
                    ->orWhere('p.part_number', 'like', $like)
                    ->orWhere('p.application', 'like', $like)
                    ->orWhere('p.Position', 'like', $like)
                    ->orWhere('p.description', 'like', $like)
                    ->orWhere('p.category', 'like', $like);
            });
        }

        if ($brand !== '') {
            $query->whereRaw("COALESCE(NULLIF(p.category, ''), NULLIF(op.category, ''), 'Other') = ?", [$brand]);
        }

        if ($description !== '') {
            $query->whereRaw("COALESCE(NULLIF(p.description, ''), NULLIF(op.description, ''), 'Other') = ?", [$description]);
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
