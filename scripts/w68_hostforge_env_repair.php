<?php

declare(strict_types=1);

// W68_HOSTFORGE_WEB_ENV_REPAIR_20261019
// Copies the live HostForge container DB settings into Laravel's .env so the
// HTTP runtime and terminal runtime resolve the same database connections.
// Secret values are never printed.

$root = dirname(__DIR__);
$envPath = $root . '/.env';

if (!is_file($envPath)) {
    touch($envPath);
}

$envText = (string) file_get_contents($envPath);

function requireProcessEnv(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Missing required HostForge environment variable: {$key}\n");
        exit(2);
    }

    return (string) $value;
}

function dotenvValue(string $value): string
{
    $value = str_replace(
        ['\\', '"', '$', "\r", "\n"],
        ['\\\\', '\\"', '\\$', '\\r', '\\n'],
        $value
    );

    return '"' . $value . '"';
}
function setDotenv(string $text, string $key, string $value): string
{
    $line = $key . '=' . dotenvValue($value);
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

    if (preg_match($pattern, $text)) {
        return (string) preg_replace($pattern, $line, $text, 1);
    }

    return rtrim($text) . PHP_EOL . $line . PHP_EOL;
}

$updates = [
    'APP_URL' => 'https://pricelist-w68autoparts-lisxk.hostforgeplatforms.com',

    'DB_CONNECTION' => requireProcessEnv('DB_CONNECTION'),
    'DB_HOST' => requireProcessEnv('DB_HOST'),
    'DB_PORT' => requireProcessEnv('DB_PORT'),
    'DB_DATABASE' => requireProcessEnv('DB_DATABASE'),
    'DB_USERNAME' => requireProcessEnv('DB_USERNAME'),
    'DB_PASSWORD' => requireProcessEnv('DB_PASSWORD'),

    // Replacement core4_system_proposal now lives in the empty default DB.
    'SYSTEM_DB_CONNECTION' => requireProcessEnv('DB_CONNECTION'),
    'SYSTEM_DB_HOST' => requireProcessEnv('DB_HOST'),
    'SYSTEM_DB_PORT' => requireProcessEnv('DB_PORT'),
    'SYSTEM_DB_DATABASE' => requireProcessEnv('DB_DATABASE'),
    'SYSTEM_DB_USERNAME' => requireProcessEnv('DB_USERNAME'),
    'SYSTEM_DB_PASSWORD' => requireProcessEnv('DB_PASSWORD'),

    'MASTERLIST_DB_CONNECTION' => requireProcessEnv('MASTERLIST_DB_CONNECTION'),
    'MASTERLIST_DB_HOST' => requireProcessEnv('MASTERLIST_DB_HOST'),
    'MASTERLIST_DB_PORT' => requireProcessEnv('MASTERLIST_DB_PORT'),
    'MASTERLIST_DB_DATABASE' => requireProcessEnv('MASTERLIST_DB_DATABASE'),
    'MASTERLIST_DB_USERNAME' => requireProcessEnv('MASTERLIST_DB_USERNAME'),
    'MASTERLIST_DB_PASSWORD' => requireProcessEnv('MASTERLIST_DB_PASSWORD'),

    'DB_SALES_HOST' => requireProcessEnv('DB_SALES_HOST'),
    'DB_SALES_PORT' => requireProcessEnv('DB_SALES_PORT'),
    'DB_SALES_DATABASE' => requireProcessEnv('DB_SALES_DATABASE'),
    'DB_SALES_USERNAME' => requireProcessEnv('DB_SALES_USERNAME'),
    'DB_SALES_PASSWORD' => requireProcessEnv('DB_SALES_PASSWORD'),

    'SESSION_DRIVER' => 'database',
    'SESSION_CONNECTION' => 'system',
    'SESSION_COOKIE' => 'w68_pricelist_customer_session_v2',
    'SESSION_PATH' => '/',
    'SESSION_SECURE_COOKIE' => 'true',
    'SESSION_SAME_SITE' => 'lax',
];

foreach ($updates as $key => $value) {
    $envText = setDotenv($envText, $key, (string) $value);
}

file_put_contents($envPath, $envText);

echo "W68 .env repaired successfully.\n";
echo "APP_URL=https://pricelist-w68autoparts-lisxk.hostforgeplatforms.com\n";
echo "SYSTEM_DB_HOST=" . requireProcessEnv('DB_HOST') . "\n";
echo "SYSTEM_DB_DATABASE=" . requireProcessEnv('DB_DATABASE') . "\n";
echo "MASTERLIST_DB_HOST=" . requireProcessEnv('MASTERLIST_DB_HOST') . "\n";
echo "MASTERLIST_DB_DATABASE=" . requireProcessEnv('MASTERLIST_DB_DATABASE') . "\n";
echo "DB_SALES_HOST=" . requireProcessEnv('DB_SALES_HOST') . "\n";
echo "DB_SALES_DATABASE=" . requireProcessEnv('DB_SALES_DATABASE') . "\n";
echo "SESSION_DRIVER=database\n";
echo "SESSION_CONNECTION=system\n";
echo "SESSION_COOKIE=w68_pricelist_customer_session_v2\n";
echo "Secrets: NOT PRINTED\n";
