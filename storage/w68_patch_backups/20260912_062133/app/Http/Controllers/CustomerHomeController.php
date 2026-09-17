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

        $targetColumn = $fieldMap[$field]['qualified'];
        $targetRaw = $fieldMap[$field]['raw'];

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
         * Product Code / Part Number are product-specific searches.
         *
         * If the typed text does not appear in the code itself (example:
         * typing "ball" in Product Code), W68 still finds related selected
         * products through Description / Application / Brand / Position.
         * Choosing the suggestion fills the REAL Product Code / Part Number
         * into the field so the final Home search remains valid.
         */
        if (in_array($field, ['product_code', 'part_number'], true)) {
            $query = DB::connection('masterlist')
                ->table('products as p')
                ->where('p.is_selected_for_report', 1)
                ->whereNotNull($targetColumn)
                ->whereRaw("TRIM({$targetColumn}) <> ''");

            $applyRelatedSearch($query);

            $items = $query
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
                ->get()
                ->map(function ($row) use ($field, $targetRaw) {
                    $value = trim((string) ($row->{$targetRaw} ?? ''));

                    if ($value === '') {
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
                        'price' => number_format(
                            (float) ($row->selling_price ?? 0),
                            2,
                            '.',
                            ''
                        ),
                        // Exact product = exact selected product picture.
                        'image' => route(
                            'home.product-image',
                            ['product' => $row->id],
                            false
                        ),
                    ];
                })
                ->filter()
                ->values();

            return response()->json(['items' => $items]);
        }

        /*
         * Description / Application / Brand / Position can represent many
         * products. Find shared values related to the typed text, then use a
         * random selected product from that value as its recommendation image.
         */
        $query = DB::connection('masterlist')
            ->table('products as p')
            ->where('p.is_selected_for_report', 1)
            ->whereNotNull($targetColumn)
            ->whereRaw("TRIM({$targetColumn}) <> ''");

        $applyRelatedSearch($query);

        $valueRows = $query
            ->selectRaw("TRIM({$targetColumn}) as recommendation_value")
            ->selectRaw(
                "(SELECT rp.id
                  FROM products rp
                  WHERE rp.is_selected_for_report = 1
                    AND TRIM(rp.{$targetRaw}) = TRIM({$targetColumn})
                  ORDER BY RAND()
                  LIMIT 1) as random_product_id"
            )
            ->groupBy(DB::raw("TRIM({$targetColumn})"))
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
                $value = trim(
                    (string) ($valueRow->recommendation_value ?? '')
                );

                $randomProductId = (int) (
                    $valueRow->random_product_id ?? 0
                );

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
                    'price' => number_format(
                        (float) ($row->selling_price ?? 0),
                        2,
                        '.',
                        ''
                    ),
                    'image' => route(
                        'home.product-image',
                        ['product' => $row->id],
                        false
                    ),
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