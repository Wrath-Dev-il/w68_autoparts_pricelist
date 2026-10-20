<?php

declare(strict_types=1);

// W68_ENSURE_CORE_SYSTEM_PORTAL_SCHEMA_20261020

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$systemConfig = (array) config('database.connections.system', []);
$legacyConfig = (array) config('database.connections.managed_legacy', []);

$systemHost = (string) ($systemConfig['host'] ?? '');
$systemDb = (string) ($systemConfig['database'] ?? '');
$legacyHost = (string) ($legacyConfig['host'] ?? '');
$legacyDb = (string) ($legacyConfig['database'] ?? '');

echo "=== W68 CORE SYSTEM PORTAL SCHEMA ===\n";
echo "SYSTEM={$systemHost}/{$systemDb}\n";
echo "MANAGED_LEGACY={$legacyHost}/{$legacyDb}\n";

if ($systemHost === '' || $systemDb === '') {
    fwrite(STDERR, "System connection is incomplete.\n");
    exit(2);
}

if ($systemHost === $legacyHost && $systemDb === $legacyDb) {
    fwrite(STDERR, "SAFETY CHECK FAILED: system still points to Managed MySQL.\n");
    exit(2);
}

$db = DB::connection('system');
$db->getPdo();
echo "SYSTEM_CONNECTED=YES\n";

$schema = Schema::connection('system');

if (!$schema->hasTable('logins')) {
    fwrite(STDERR, "CORE LOGINS TABLE=MISSING. Import/restore core4_system_proposal first.\n");
    exit(3);
}

if (!$schema->hasTable('customer_portal_accounts')) {
    $db->statement(<<<'SQL'
CREATE TABLE customer_portal_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    login_id BIGINT UNSIGNED NOT NULL,
    authorization_id BIGINT UNSIGNED DEFAULT NULL,
    linked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY customer_portal_accounts_customer_id_unique (customer_id),
    UNIQUE KEY customer_portal_accounts_login_id_unique (login_id),
    KEY customer_portal_accounts_authorization_id_index (authorization_id),
    CONSTRAINT customer_portal_accounts_login_id_foreign
        FOREIGN KEY (login_id) REFERENCES logins (login_ID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

if (!$schema->hasTable('customer_portal_notifications')) {
    $db->statement(<<<'SQL'
CREATE TABLE customer_portal_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_key VARCHAR(191) NOT NULL,
    title VARCHAR(191) NOT NULL,
    message TEXT DEFAULT NULL,
    event_at TIMESTAMP NULL DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY customer_portal_notifications_event_unique (event_key),
    KEY customer_portal_generic_owner_unread_idx (login_id, customer_id, is_read),
    KEY customer_portal_generic_customer_event_idx (customer_id, event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

if (!$schema->hasTable('sessions')) {
    $db->statement(<<<'SQL'
CREATE TABLE sessions (
    id VARCHAR(255) NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    PRIMARY KEY (id),
    KEY sessions_user_id_index (user_id),
    KEY sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

foreach (['logins', 'customer_portal_accounts', 'customer_portal_notifications', 'sessions'] as $table) {
    echo strtoupper($table) . '=' . ($schema->hasTable($table) ? 'FOUND' : 'MISSING') . "\n";
}

echo "CORE_CUSTOMER_LOGINS=" . $db->table('logins')->where('account_type', 5)->count() . "\n";
echo "CORE_PORTAL_LINKS=" . $db->table('customer_portal_accounts')->count() . "\n";
echo "CORE_NOTIFICATIONS=" . $db->table('customer_portal_notifications')->count() . "\n";
echo "SCHEMA_READY=YES\n";
