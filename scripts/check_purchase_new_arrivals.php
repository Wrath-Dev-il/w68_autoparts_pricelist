<?php

declare(strict_types=1);

// W68_CHECK_PURCHASE_NEW_ARRIVALS_20261024

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$config = (array) config('database.connections.purchase', []);

echo "=== W68 PURCHASE / NEW ARRIVAL CHECK ===\n";
echo "PURCHASE_HOST=" . ($config['host'] ?? '') . "\n";
echo "PURCHASE_DATABASE=" . ($config['database'] ?? '') . "\n";

$db = DB::connection('purchase');
$db->getPdo();
echo "PURCHASE_CONNECTED=YES\n";

$schema = Schema::connection('purchase');

if (!$schema->hasTable('purchase_order_items')) {
    fwrite(STDERR, "PURCHASE_ORDER_ITEMS=MISSING\n");
    exit(2);
}

foreach (['product_id', 'created_at'] as $column) {
    if (!$schema->hasColumn('purchase_order_items', $column)) {
        fwrite(STDERR, "PURCHASE_ORDER_ITEMS_COLUMN_" . strtoupper($column) . "=MISSING\n");
        exit(2);
    }
}

$cutoff = now()->subMonthsNoOverflow(3);

$rows = $db->table('purchase_order_items')
    ->whereNotNull('product_id')
    ->where('product_id', '>', 0)
    ->whereNotNull('created_at')
    ->where('created_at', '>=', $cutoff)
    ->select('product_id')
    ->selectRaw('MAX(created_at) as latest_purchase_at')
    ->groupBy('product_id')
    ->orderByDesc('latest_purchase_at')
    ->get();

echo "NEW_ARRIVAL_CUTOFF=" . $cutoff->format('Y-m-d H:i:s') . "\n";
echo "QUALIFYING_PRODUCT_IDS=" . $rows->count() . "\n";

foreach ($rows->take(12) as $row) {
    echo "PRODUCT_ID=" . (int) $row->product_id
        . " | LATEST_PURCHASE=" . (string) $row->latest_purchase_at
        . "\n";
}

echo "SUCCESS=YES\n";
