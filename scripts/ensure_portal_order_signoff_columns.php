<?php

declare(strict_types=1);

// W68_PORTAL_ORDER_SIGNOFF_COLUMNS_20261021

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$config = (array) config('database.connections.sales_order', []);
echo "=== W68 PORTAL ORDER SIGN-OFF SCHEMA ===\n";
echo "SALES_ORDER_HOST=" . ($config['host'] ?? '') . "\n";
echo "SALES_ORDER_DATABASE=" . ($config['database'] ?? '') . "\n";

$db = DB::connection('sales_order');
$db->getPdo();
echo "SALES_ORDER_CONNECTED=YES\n";

$schema = Schema::connection('sales_order');

if (!$schema->hasTable('w68_portal_orders')) {
    fwrite(STDERR, "W68_PORTAL_ORDERS=MISSING\n");
    exit(2);
}

if (!$schema->hasColumn('w68_portal_orders', 'prepared_by')) {
    $db->statement(
        "ALTER TABLE `w68_portal_orders` ADD COLUMN `prepared_by` VARCHAR(191) NULL AFTER `portal_status`"
    );
    echo "PREPARED_BY=CREATED\n";
} else {
    echo "PREPARED_BY=FOUND\n";
}

if (!$schema->hasColumn('w68_portal_orders', 'checked_by')) {
    $db->statement(
        "ALTER TABLE `w68_portal_orders` ADD COLUMN `checked_by` VARCHAR(191) NULL AFTER `prepared_by`"
    );
    echo "CHECKED_BY=CREATED\n";
} else {
    echo "CHECKED_BY=FOUND\n";
}

echo "PREPARED_BY_FINAL=" . ($schema->hasColumn('w68_portal_orders', 'prepared_by') ? 'FOUND' : 'MISSING') . "\n";
echo "CHECKED_BY_FINAL=" . ($schema->hasColumn('w68_portal_orders', 'checked_by') ? 'FOUND' : 'MISSING') . "\n";
echo "SUCCESS=YES\n";
