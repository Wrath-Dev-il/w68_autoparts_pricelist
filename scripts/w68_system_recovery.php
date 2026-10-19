<?php

declare(strict_types=1);

// W68_SYSTEM_RECOVERY_RUNNER_20261019
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tokenHash = trim((string) getenv('W68_TOKEN_HASH'));
if ($tokenHash === '') {
    fwrite(STDERR, "W68_TOKEN_HASH is required.\n");
    exit(4);
}

$system = config('database.connections.system');
$default = config('database.connections.mysql');

echo "=== W68 SYSTEM RECOVERY ===\n";
echo "SYSTEM={$system['host']}/{$system['database']}\n";
echo "DEFAULT={$default['host']}/{$default['database']}\n";

if (
    (string) $system['host'] !== (string) $default['host']
    || (string) $system['database'] !== (string) $default['database']
) {
    fwrite(STDERR, "SAFETY CHECK FAILED: system connection is not the empty default HostForge database.\n");
    exit(2);
}

$db = DB::connection('system');
$db->getPdo();
echo "SYSTEM CONNECTED=YES\n";

$schema = Schema::connection('system');

if (!$schema->hasTable('logins')) {
    $db->statement("CREATE TABLE `logins` (`login_ID` bigint unsigned NOT NULL AUTO_INCREMENT,`account_type` int NOT NULL DEFAULT 3,`User_ID` varchar(255) NOT NULL,`Email` varchar(255) DEFAULT NULL,`OTP_CODE` int DEFAULT NULL,`Password` varchar(255) NOT NULL,`User_First_Name` varchar(255) NOT NULL,`User_Middle_Name` varchar(255) DEFAULT NULL,`User_Last_Name` varchar(255) NOT NULL,`Gender` varchar(255) NOT NULL,`profile_picture_mime` varchar(255) DEFAULT NULL,`profile_picture` longblob DEFAULT NULL,`created_at` timestamp NULL DEFAULT NULL,`updated_at` timestamp NULL DEFAULT NULL,PRIMARY KEY (`login_ID`),UNIQUE KEY `logins_user_id_unique` (`User_ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (!$schema->hasTable('customer_portal_accounts')) {
    $db->statement("CREATE TABLE `customer_portal_accounts` (`id` bigint unsigned NOT NULL AUTO_INCREMENT,`customer_id` bigint unsigned NOT NULL,`login_id` bigint unsigned NOT NULL,`authorization_id` bigint unsigned DEFAULT NULL,`linked_at` datetime DEFAULT NULL,`created_at` timestamp NULL DEFAULT NULL,`updated_at` timestamp NULL DEFAULT NULL,PRIMARY KEY (`id`),UNIQUE KEY `customer_portal_accounts_customer_id_unique` (`customer_id`),UNIQUE KEY `customer_portal_accounts_login_id_unique` (`login_id`),KEY `customer_portal_accounts_authorization_id_index` (`authorization_id`),CONSTRAINT `customer_portal_accounts_login_id_foreign` FOREIGN KEY (`login_id`) REFERENCES `logins` (`login_ID`) ON UPDATE CASCADE ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (!$schema->hasTable('sessions')) {
    $db->statement("CREATE TABLE `sessions` (`id` varchar(255) NOT NULL,`user_id` bigint unsigned DEFAULT NULL,`ip_address` varchar(45) DEFAULT NULL,`user_agent` text DEFAULT NULL,`payload` longtext NOT NULL,`last_activity` int NOT NULL,PRIMARY KEY (`id`),KEY `sessions_user_id_index` (`user_id`),KEY `sessions_last_activity_index` (`last_activity`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

foreach (['logins', 'customer_portal_accounts', 'sessions'] as $table) {
    echo strtoupper($table) . '=' . ($schema->hasTable($table) ? 'FOUND' : 'MISSING') . "\n";
}

$master = DB::connection('masterlist');
$master->getPdo();
echo "MASTERLIST CONNECTED=YES\n";

$authorization = $master
    ->table('customer_portal_authorizations')
    ->where('token_hash', $tokenHash)
    ->first();

if (!$authorization) {
    echo "TOKEN=NOT FOUND\n";
    exit(3);
}

echo "TOKEN=FOUND\n";
echo "AUTH_ID={$authorization->id}\n";
echo "CUSTOMER_ID={$authorization->customer_id}\n";
echo "EXPIRES={$authorization->expires_at}\n";
echo "STATUS=" . (now()->greaterThanOrEqualTo($authorization->expires_at) ? 'EXPIRED' : 'ACTIVE') . "\n";

$customerExists = $master
    ->table('customers')
    ->where('id', $authorization->customer_id)
    ->exists();

echo "CUSTOMER=" . ($customerExists ? 'FOUND' : 'MISSING') . "\n";
echo "=== RECOVERY CHECK COMPLETE ===\n";
