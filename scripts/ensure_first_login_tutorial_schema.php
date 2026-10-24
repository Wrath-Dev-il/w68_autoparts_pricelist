<?php

declare(strict_types=1);

// W68_FIRST_LOGIN_TUTORIAL_SCHEMA_20261024

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== W68 FIRST-3-LOGIN TUTORIAL SCHEMA ===\n";

$db = DB::connection('system');
$db->getPdo();
echo "SYSTEM_CONNECTED=YES\n";

$schema = Schema::connection('system');

if (!$schema->hasTable('customer_portal_accounts')) {
    fwrite(STDERR, "CUSTOMER_PORTAL_ACCOUNTS=MISSING\n");
    exit(2);
}

if (!$schema->hasColumn('customer_portal_accounts', 'tutorial_login_count')) {
    $db->statement(
        "ALTER TABLE `customer_portal_accounts` "
        . "ADD COLUMN `tutorial_login_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `authorization_id`"
    );
    echo "TUTORIAL_LOGIN_COUNT=CREATED\n";
} else {
    echo "TUTORIAL_LOGIN_COUNT=FOUND\n";
}

echo "COLUMN_FINAL="
    . ($schema->hasColumn('customer_portal_accounts', 'tutorial_login_count') ? 'FOUND' : 'MISSING')
    . "\n";
echo "SUCCESS=YES\n";
