<?php

declare(strict_types=1);

// W68_CONFIGURE_PURCHASE_DB_20261024
// Persist the dedicated core4_purchase connection in /app/.env.
// The password is read interactively from STDIN and is never printed.

$envPath = dirname(__DIR__) . '/.env';

$host = 'mariadb-foynozbk.internal';
$port = '3306';
$database = 'hf_db_foynozbk';
$username = 'hf_p2zstvllgp';

fwrite(STDOUT, "Paste core4_purchase DB password: ");
$password = trim((string) fgets(STDIN));

if ($password === '') {
    fwrite(STDERR, "Password cannot be empty.\n");
    exit(2);
}

$env = is_file($envPath) ? (string) file_get_contents($envPath) : '';

$quote = static function (string $value): string {
    $value = str_replace(
        ['\\', '"', '$', "\r", "\n"],
        ['\\\\', '\\"', '\\$', '\\r', '\\n'],
        $value
    );

    return '"' . $value . '"';
};

$set = static function (string $text, string $key, string $value) use ($quote): string {
    $line = $key . '=' . $quote($value);
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

    if (preg_match($pattern, $text)) {
        return (string) preg_replace($pattern, $line, $text, 1);
    }

    return rtrim($text) . PHP_EOL . $line . PHP_EOL;
};

foreach ([
    'PURCHASE_DB_CONNECTION' => 'mysql',
    'DB_PURCHASE_HOST' => $host,
    'DB_PURCHASE_PORT' => $port,
    'DB_PURCHASE_DATABASE' => $database,
    'DB_PURCHASE_USERNAME' => $username,
    'DB_PURCHASE_PASSWORD' => $password,
] as $key => $value) {
    $env = $set($env, $key, $value);
}

if (file_put_contents($envPath, $env) === false) {
    fwrite(STDERR, "Unable to write {$envPath}.\n");
    exit(3);
}

echo "\nPURCHASE_DB_CONFIGURED=YES\n";
echo "HOST={$host}\n";
echo "PORT={$port}\n";
echo "DATABASE={$database}\n";
echo "USERNAME={$username}\n";
echo "PASSWORD=***HIDDEN***\n";
