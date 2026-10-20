<?php

declare(strict_types=1);

// W68_CONFIGURE_CORE_SYSTEM_DB_20261020
// Persist the dedicated W68 core system DB credentials without changing the
// Pricelist Managed MySQL DB_* values. Password comes from process env and is
// never printed.

$root = dirname(__DIR__);
$envPath = $root . '/.env';

$required = [
    'W68_CORE_INPUT_HOST',
    'W68_CORE_INPUT_PORT',
    'W68_CORE_INPUT_DATABASE',
    'W68_CORE_INPUT_USERNAME',
    'W68_CORE_INPUT_PASSWORD',
];

$values = [];
foreach ($required as $key) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Missing required input: {$key}\n");
        exit(2);
    }
    $values[$key] = (string) $value;
}

$env = is_file($envPath) ? (string) file_get_contents($envPath) : '';

$set = static function (string $text, string $key, string $value): string {
    $escaped = str_replace(
        ['\\', '"', '$', "\r", "\n"],
        ['\\\\', '\\"', '\\$', '\\r', '\\n'],
        $value
    );
    $line = $key . '="' . $escaped . '"';
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

    if (preg_match($pattern, $text)) {
        return (string) preg_replace($pattern, $line, $text, 1);
    }

    return rtrim($text) . PHP_EOL . $line . PHP_EOL;
};

$updates = [
    'W68_CORE_SYSTEM_DB_CONNECTION' => 'mysql',
    // Explicit empty URL prevents an old SYSTEM_DB_URL from overriding fields.
    'W68_CORE_SYSTEM_DB_URL' => '',
    'W68_CORE_SYSTEM_DB_HOST' => $values['W68_CORE_INPUT_HOST'],
    'W68_CORE_SYSTEM_DB_PORT' => $values['W68_CORE_INPUT_PORT'],
    'W68_CORE_SYSTEM_DB_DATABASE' => $values['W68_CORE_INPUT_DATABASE'],
    'W68_CORE_SYSTEM_DB_USERNAME' => $values['W68_CORE_INPUT_USERNAME'],
    'W68_CORE_SYSTEM_DB_PASSWORD' => $values['W68_CORE_INPUT_PASSWORD'],
    'SESSION_DRIVER' => 'database',
    'SESSION_CONNECTION' => 'system',
    'SESSION_TABLE' => 'sessions',
];

foreach ($updates as $key => $value) {
    $env = $set($env, $key, $value);
}

if (file_put_contents($envPath, $env) === false) {
    fwrite(STDERR, "Unable to write {$envPath}.\n");
    exit(3);
}

echo "CORE SYSTEM .env UPDATED=YES\n";
echo "HOST={$values['W68_CORE_INPUT_HOST']}\n";
echo "PORT={$values['W68_CORE_INPUT_PORT']}\n";
echo "DATABASE={$values['W68_CORE_INPUT_DATABASE']}\n";
echo "USERNAME={$values['W68_CORE_INPUT_USERNAME']}\n";
echo "PASSWORD=***HIDDEN***\n";
