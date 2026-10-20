<?php

declare(strict_types=1);

// W68_MANAGED_TO_CORE_PORTAL_MIGRATION_20261020
//
// Preview:
//   php scripts/migrate_managed_portal_to_core_system.php
//
// Apply:
//   php scripts/migrate_managed_portal_to_core_system.php --apply
//
// Moves customer account_type=5 portal records from Pricelist Managed MySQL
// into the shared W68 core system database. Sessions are intentionally not
// copied.

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);

$legacyConfig = (array) config('database.connections.managed_legacy', []);
$systemConfig = (array) config('database.connections.system', []);

$legacyHost = (string) ($legacyConfig['host'] ?? '');
$legacyDb = (string) ($legacyConfig['database'] ?? '');
$systemHost = (string) ($systemConfig['host'] ?? '');
$systemDb = (string) ($systemConfig['database'] ?? '');

echo "=== W68 PRICELIST MANAGED -> CORE SYSTEM MIGRATION ===\n";
echo "MODE=" . ($apply ? 'APPLY' : 'PREVIEW') . "\n";
echo "LEGACY={$legacyHost}/{$legacyDb}\n";
echo "SYSTEM={$systemHost}/{$systemDb}\n";

if ($systemHost === '' || $systemDb === '') {
    fwrite(STDERR, "SYSTEM connection is incomplete. Configure CORE_SYSTEM_DB_* / W68_CORE_SYSTEM_DB_* first.\n");
    exit(2);
}

if ($legacyHost === $systemHost && $legacyDb === $systemDb) {
    fwrite(STDERR, "SAFETY CHECK FAILED: managed_legacy and system resolve to the same database.\n");
    exit(2);
}

$legacy = DB::connection('managed_legacy');
$system = DB::connection('system');

$legacy->getPdo();
$system->getPdo();

echo "LEGACY_CONNECTED=YES\n";
echo "SYSTEM_CONNECTED=YES\n";

$legacySchema = Schema::connection('managed_legacy');
$systemSchema = Schema::connection('system');

if (!$legacySchema->hasTable('logins')) {
    fwrite(STDERR, "Legacy Managed MySQL logins table is missing.\n");
    exit(3);
}

if (!$systemSchema->hasTable('logins')) {
    fwrite(STDERR, "Target core system logins table is missing. Run the main W68 system migrations/import first.\n");
    exit(3);
}

$ensureTargetTables = static function () use ($system, $systemSchema): void {
    if (!$systemSchema->hasTable('customer_portal_accounts')) {
        $system->statement(<<<'SQL'
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

    if (!$systemSchema->hasTable('customer_portal_notifications')) {
        $system->statement(<<<'SQL'
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
};

$legacyAccounts = $legacy->table('logins')
    ->where('account_type', 5)
    ->orderBy('login_ID')
    ->get();

$legacyLinks = $legacySchema->hasTable('customer_portal_accounts')
    ? $legacy->table('customer_portal_accounts')->orderBy('id')->get()
    : collect();

$legacyNotifications = $legacySchema->hasTable('customer_portal_notifications')
    ? $legacy->table('customer_portal_notifications')->orderBy('id')->get()
    : collect();

echo "LEGACY_CUSTOMER_LOGINS={$legacyAccounts->count()}\n";
echo "LEGACY_PORTAL_LINKS={$legacyLinks->count()}\n";
echo "LEGACY_NOTIFICATIONS={$legacyNotifications->count()}\n";

$plan = [];
$conflicts = [];

foreach ($legacyAccounts as $account) {
    $legacyLoginId = (int) $account->login_ID;
    $email = mb_strtolower(trim((string) ($account->Email ?? '')));
    $userId = trim((string) ($account->User_ID ?? ''));

    $byEmail = null;
    if ($email !== '') {
        $byEmail = $system->table('logins')
            ->whereRaw('LOWER(Email) = ?', [$email])
            ->first();
    }

    $byUserId = null;
    if ($userId !== '') {
        $byUserId = $system->table('logins')
            ->where('User_ID', $userId)
            ->first();
    }

    if ($byEmail && $byUserId && (int) $byEmail->login_ID !== (int) $byUserId->login_ID) {
        $conflicts[] = "Legacy login {$legacyLoginId}: Email and User_ID resolve to different core login rows.";
        continue;
    }

    $existing = $byEmail ?: $byUserId;

    if ($existing && (int) ($existing->account_type ?? 0) !== 5) {
        $conflicts[] = "Legacy login {$legacyLoginId}: matching core login is not account_type=5.";
        continue;
    }

    $plan[$legacyLoginId] = [
        'legacy' => $account,
        'target_login_id' => $existing ? (int) $existing->login_ID : null,
        'action' => $existing ? 'reuse' : 'insert',
    ];
}

foreach ($legacyLinks as $link) {
    $legacyLoginId = (int) ($link->login_id ?? 0);
    if ($legacyLoginId > 0 && !isset($plan[$legacyLoginId])) {
        $conflicts[] = "Portal link #{$link->id} references legacy login {$legacyLoginId}, which is not a migratable account_type=5 login.";
    }
}

echo "LOGIN_INSERTS=" . count(array_filter($plan, fn ($row) => $row['action'] === 'insert')) . "\n";
echo "LOGIN_REUSES=" . count(array_filter($plan, fn ($row) => $row['action'] === 'reuse')) . "\n";
echo "CONFLICTS=" . count($conflicts) . "\n";

foreach ($conflicts as $conflict) {
    echo "CONFLICT={$conflict}\n";
}

if ($conflicts !== []) {
    echo "APPLY_BLOCKED=YES\n";
    exit(4);
}

if (!$apply) {
    echo "PREVIEW_COMPLETE=YES\n";
    echo "APPLY_READY=YES\n";
    exit(0);
}

$ensureTargetTables();

$targetLoginColumns = array_fill_keys($systemSchema->getColumnListing('logins'), true);

$loginMap = [];
$insertedLogins = 0;
$reusedLogins = 0;
$linkedAccounts = 0;
$insertedNotifications = 0;
$skippedNotifications = 0;

$system->transaction(function () use (
    $system,
    $targetLoginColumns,
    $plan,
    $legacyLinks,
    $legacyNotifications,
    &$loginMap,
    &$insertedLogins,
    &$reusedLogins,
    &$linkedAccounts,
    &$insertedNotifications,
    &$skippedNotifications
): void {
    foreach ($plan as $legacyLoginId => $row) {
        if ($row['target_login_id']) {
            $loginMap[$legacyLoginId] = (int) $row['target_login_id'];
            $reusedLogins++;
            continue;
        }

        $account = $row['legacy'];
        $payload = [];

        foreach ([
            'account_type',
            'User_ID',
            'Email',
            'Password',
            'User_First_Name',
            'User_Middle_Name',
            'User_Last_Name',
            'Gender',
            'profile_picture_mime',
            'profile_picture',
            'created_at',
            'updated_at',
        ] as $column) {
            if (isset($targetLoginColumns[$column])) {
                $payload[$column] = $account->{$column} ?? null;
            }
        }

        if (isset($targetLoginColumns['OTP_CODE'])) {
            $payload['OTP_CODE'] = null;
        }

        $targetLoginId = (int) $system->table('logins')->insertGetId($payload, 'login_ID');
        $loginMap[$legacyLoginId] = $targetLoginId;
        $insertedLogins++;
    }

    foreach ($legacyLinks as $link) {
        $legacyLoginId = (int) ($link->login_id ?? 0);
        $targetLoginId = (int) ($loginMap[$legacyLoginId] ?? 0);
        $customerId = (int) ($link->customer_id ?? 0);

        if ($targetLoginId <= 0 || $customerId <= 0) {
            continue;
        }

        $byLogin = $system->table('customer_portal_accounts')
            ->where('login_id', $targetLoginId)
            ->lockForUpdate()
            ->first();

        $byCustomer = $system->table('customer_portal_accounts')
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->first();

        if ($byLogin && (int) $byLogin->customer_id !== $customerId) {
            throw new RuntimeException("Core login {$targetLoginId} is already linked to customer {$byLogin->customer_id}, not {$customerId}.");
        }

        if ($byCustomer && (int) $byCustomer->login_id !== $targetLoginId) {
            throw new RuntimeException("Core customer {$customerId} is already linked to login {$byCustomer->login_id}, not {$targetLoginId}.");
        }

        $existing = $byLogin ?: $byCustomer;

        if ($existing) {
            $system->table('customer_portal_accounts')
                ->where('id', $existing->id)
                ->update([
                    'authorization_id' => $link->authorization_id ?? $existing->authorization_id,
                    'linked_at' => $existing->linked_at ?? $link->linked_at ?? now(),
                    'updated_at' => now(),
                ]);
        } else {
            $system->table('customer_portal_accounts')->insert([
                'customer_id' => $customerId,
                'login_id' => $targetLoginId,
                'authorization_id' => $link->authorization_id ?? null,
                'linked_at' => $link->linked_at ?? now(),
                'created_at' => $link->created_at ?? now(),
                'updated_at' => $link->updated_at ?? now(),
            ]);
        }

        $linkedAccounts++;
    }

    foreach ($legacyNotifications as $notification) {
        $legacyLoginId = (int) ($notification->login_id ?? 0);
        $targetLoginId = (int) ($loginMap[$legacyLoginId] ?? 0);

        if ($targetLoginId <= 0) {
            continue;
        }

        $eventKey = trim((string) ($notification->event_key ?? ''));
        if ($eventKey === '') {
            continue;
        }

        $exists = $system->table('customer_portal_notifications')
            ->where('event_key', $eventKey)
            ->exists();

        if ($exists) {
            $skippedNotifications++;
            continue;
        }

        $system->table('customer_portal_notifications')->insert([
            'login_id' => $targetLoginId,
            'customer_id' => (int) ($notification->customer_id ?? 0),
            'event_type' => (string) ($notification->event_type ?? ''),
            'event_key' => $eventKey,
            'title' => (string) ($notification->title ?? ''),
            'message' => $notification->message ?? null,
            'event_at' => $notification->event_at ?? null,
            'is_read' => (int) ($notification->is_read ?? 0),
            'read_at' => $notification->read_at ?? null,
            'created_at' => $notification->created_at ?? now(),
            'updated_at' => $notification->updated_at ?? now(),
        ]);

        $insertedNotifications++;
    }
});

echo "MIGRATED_LOGIN_INSERTS={$insertedLogins}\n";
echo "MIGRATED_LOGIN_REUSES={$reusedLogins}\n";
echo "MIGRATED_PORTAL_LINKS={$linkedAccounts}\n";
echo "MIGRATED_NOTIFICATIONS={$insertedNotifications}\n";
echo "SKIPPED_EXISTING_NOTIFICATIONS={$skippedNotifications}\n";
echo "TARGET_CUSTOMER_LOGINS=" . $system->table('logins')->where('account_type', 5)->count() . "\n";
echo "TARGET_PORTAL_LINKS=" . $system->table('customer_portal_accounts')->count() . "\n";
echo "TARGET_NOTIFICATIONS=" . $system->table('customer_portal_notifications')->count() . "\n";
echo "SUCCESS=YES\n";
