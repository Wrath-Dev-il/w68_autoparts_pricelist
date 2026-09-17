<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
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

        try {
            $catalog = $this->catalogQuery();
            $this->applyFilters($catalog, $filters);

            // The customer catalog intentionally shows at least 100 products per page.
            $products = $catalog
                ->orderByDesc('p.date_added')
                ->orderByDesc('p.id')
                ->paginate(100)
                ->withQueryString();

            // New Items use ONLY products selected for this website.
            $newItems = $this->catalogQuery()
                ->orderByRaw("CASE WHEN p.status = 'Newly' THEN 0 ELSE 1 END")
                ->orderByDesc('p.date_added')
                ->orderByDesc('p.id')
                ->limit(12)
                ->get();

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
            'product_code' => ['qualified' => 'p.product_code', 'raw' => 'product_code'],
            'part_number' => ['qualified' => 'p.part_number', 'raw' => 'part_number'],
            'description' => ['qualified' => 'p.description', 'raw' => 'description'],
            'application' => ['qualified' => 'p.application', 'raw' => 'application'],
            'brand' => ['qualified' => 'p.category', 'raw' => 'category'],
            'position' => ['qualified' => 'p.Position', 'raw' => 'Position'],
        ];

        if (mb_strlen($term) < 1 || !isset($fieldMap[$field])) {
            return response()->json(['items' => []]);
        }

        $column = $fieldMap[$field]['qualified'];
        $rawColumn = $fieldMap[$field]['raw'];
        $escaped = $this->escapeLike($term);
        $contains = '%' . $escaped . '%';
        $starts = $escaped . '%';

        /*
         * Product Code and Part Number identify actual products, therefore
         * each recommendation uses that exact product's selected image.
         */
        if (in_array($field, ['product_code', 'part_number'], true)) {
            $items = DB::connection('masterlist')
                ->table('products as p')
                ->where('p.is_selected_for_report', 1)
                ->whereNotNull($column)
                ->whereRaw("TRIM({$column}) <> ''")
                ->where($column, 'like', $contains)
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
                ->orderByRaw("CASE WHEN TRIM({$column}) LIKE ? THEN 0 ELSE 1 END", [$starts])
                ->orderBy($column)
                ->limit(12)
                ->get()
                ->map(function ($row) use ($field, $rawColumn) {
                    $value = trim((string) ($row->{$rawColumn} ?? ''));

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
                        'price' => number_format((float) ($row->selling_price ?? 0), 2, '.', ''),
                        'image' => route('home.product-image', ['product' => $row->id], false),
                    ];
                })
                ->filter(fn ($item) => $item['value'] !== '')
                ->values();

            return response()->json(['items' => $items]);
        }

        /*
         * Brand / Application / Position / Description can represent many
         * products. Show a random selected product image for each matching
         * shared value so recommendations stay visually useful.
         */
        $valueRows = DB::connection('masterlist')
            ->table('products as p')
            ->where('p.is_selected_for_report', 1)
            ->whereNotNull($column)
            ->whereRaw("TRIM({$column}) <> ''")
            ->where($column, 'like', $contains)
            ->selectRaw("TRIM({$column}) as recommendation_value")
            ->selectRaw(
                "(SELECT rp.id
                  FROM products rp
                  WHERE rp.is_selected_for_report = 1
                    AND TRIM(rp.{$rawColumn}) = TRIM({$column})
                  ORDER BY RAND()
                  LIMIT 1) as random_product_id"
            )
            ->groupBy(DB::raw("TRIM({$column})"))
            ->orderByRaw("CASE WHEN TRIM({$column}) LIKE ? THEN 0 ELSE 1 END", [$starts])
            ->orderByRaw("TRIM({$column})")
            ->limit(12)
            ->get();

        $randomProductIds = $valueRows
            ->pluck('random_product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $representatives = DB::connection('masterlist')
            ->table('products as p')
            ->where('p.is_selected_for_report', 1)
            ->whereIn('p.id', $randomProductIds)
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
            ->get()
            ->keyBy('id');

        $items = $valueRows
            ->map(function ($valueRow) use ($field, $representatives) {
                $value = trim((string) ($valueRow->recommendation_value ?? ''));
                $randomProductId = (int) ($valueRow->random_product_id ?? 0);
                $row = $representatives->get($randomProductId);

                if ($value === '' || !$row) {
                    return null;
                }

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
                    'price' => number_format((float) ($row->selling_price ?? 0), 2, '.', ''),
                    'image' => route('home.product-image', ['product' => $row->id], false),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Serves the cover image selected in Hatdog Product Master.
     * Hatdog moves the chosen cover image to index 0 in Product_Picture,
     * so W68 uses the first valid image from that field.
     */
    public function productImage(int $product)
    {
        $this->ensureAccountTypeFive();

        $picture = DB::connection('masterlist')
            ->table('products')
            ->where('id', $product)
            ->where('is_selected_for_report', 1)
            ->value('Product_Picture');

        return $this->imageResponse($picture);
    }

    public function profilePicture()
    {
        $account = $this->ensureAccountTypeFive();
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

            return $this->imageResponse($picture);
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
        if ($value === null || $value === '') {
            return $this->fallbackLogoResponse();
        }

        if (!is_string($value)) {
            return $this->fallbackLogoResponse();
        }

        $candidate = $value;
        $firstByte = $candidate[0] ?? '';

        // Raw binary image from the longblob.
        if ($this->looksLikeBinaryImage($candidate)) {
            return response($candidate, 200, [
                'Content-Type' => $this->detectImageMime($candidate),
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        $trimmed = trim($candidate);

        // Product Master stores the selected cover at index 0.
        if ($firstByte === '[' || $firstByte === '{' || str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            $selected = $this->firstImageFromDecodedValue($decoded);

            if ($selected !== null) {
                return $this->imageResponse($selected);
            }

            return $this->fallbackLogoResponse();
        }

        // data:image/...;base64,...
        if (preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#is', $trimmed, $matches)) {
            $binary = base64_decode($matches[2], true);

            if ($binary !== false) {
                return response($binary, 200, [
                    'Content-Type' => strtolower($matches[1]),
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            }
        }

        // Remote URL stored by Product Master.
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $url = $trimmed;
            $parts = parse_url($trimmed);
            $host = strtolower((string) ($parts['host'] ?? ''));

            // Product Master may have saved a localhost URL. Reuse the
            // current request host so the same selected image also works
            // from the user's iPad / LAN address.
            if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                $url = request()->getSchemeAndHttpHost()
                    . (string) ($parts['path'] ?? '');

                if (!empty($parts['query'])) {
                    $url .= '?' . $parts['query'];
                }
            }

            return redirect()->away($url);
        }

        // Local path when the file is also present in this project's public folder.
        $relative = ltrim($trimmed, '/\\');
        $localPath = public_path($relative);

        if ($relative !== '' && is_file($localPath)) {
            return response()->file($localPath, [
                'Cache-Control' => 'private, max-age=3600',
            ]);
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
                foreach (['url', 'src', 'image', 'image_url'] as $key) {
                    if (!empty($value[$key]) && is_string($value[$key])) {
                        return $value[$key];
                    }
                }
            }
        }

        return null;
    }

    private function looksLikeBinaryImage(string $value): bool
    {
        $length = strlen($value);

        if ($length >= 8 && substr($value, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return true;
        }

        if ($length >= 3 && substr($value, 0, 3) === "\xFF\xD8\xFF") {
            return true;
        }

        if ($length >= 12 && substr($value, 0, 4) === 'RIFF' && substr($value, 8, 4) === 'WEBP') {
            return true;
        }

        if ($length >= 6 && (substr($value, 0, 6) === 'GIF87a' || substr($value, 0, 6) === 'GIF89a')) {
            return true;
        }

        return false;
    }

    private function detectImageMime(string $binary): string
    {
        if (substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'image/png';
        }

        if (substr($binary, 0, 3) === "\xFF\xD8\xFF") {
            return 'image/jpeg';
        }

        if (strlen($binary) >= 12 && substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        if (str_starts_with($binary, 'GIF8')) {
            return 'image/gif';
        }

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($binary);

            if (is_string($mime) && str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }

        return 'image/jpeg';
    }

    private function fallbackLogoResponse()
    {
        $path = public_path('build/assets/images/sidebar_logo.png');

        if (is_file($path)) {
            return response()->file($path, [
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        abort(404);
    }
}
