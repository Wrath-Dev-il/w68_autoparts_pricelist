<?php

use Illuminate\Support\Str;
use Pdo\Mysql;


$isHostForge = str_contains((string) ($_SERVER['HTTP_HOST'] ?? ''), 'hostforgeplatforms.com')
    || str_contains((string) env('APP_URL', ''), 'hostforgeplatforms.com');

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'system' => [
            // W68_SHARED_CORE_SYSTEM_DB_20261020
            // Pricelist authentication, customer_portal_accounts and
            // customer_portal_notifications MUST use the same core system DB
            // as the main W68 application. The HostForge app's default DB_*
            // variables belong to the Pricelist Managed MySQL database and
            // must never silently become the system/auth database.
            'driver' => env(
                'W68_CORE_SYSTEM_DB_CONNECTION',
                env('CORE_SYSTEM_DB_CONNECTION', env('SYSTEM_DB_CONNECTION', 'mysql'))
            ),
            'url' => env(
                'W68_CORE_SYSTEM_DB_URL',
                env('CORE_SYSTEM_DB_URL', env('SYSTEM_DB_URL'))
            ),
            'host' => env(
                'W68_CORE_SYSTEM_DB_HOST',
                env('CORE_SYSTEM_DB_HOST', env('SYSTEM_DB_HOST', '127.0.0.1'))
            ),
            'port' => env(
                'W68_CORE_SYSTEM_DB_PORT',
                env('CORE_SYSTEM_DB_PORT', env('SYSTEM_DB_PORT', '3306'))
            ),
            'database' => env(
                'W68_CORE_SYSTEM_DB_DATABASE',
                env('CORE_SYSTEM_DB_DATABASE', env('SYSTEM_DB_DATABASE', 'core4_system_proposal'))
            ),
            'username' => env(
                'W68_CORE_SYSTEM_DB_USERNAME',
                env('CORE_SYSTEM_DB_USERNAME', env('SYSTEM_DB_USERNAME', 'root'))
            ),
            'password' => env(
                'W68_CORE_SYSTEM_DB_PASSWORD',
                env('CORE_SYSTEM_DB_PASSWORD', env('SYSTEM_DB_PASSWORD', ''))
            ),
            'unix_socket' => env('SYSTEM_DB_SOCKET', ''),
            'charset' => env('SYSTEM_DB_CHARSET', 'utf8mb4'),
            'collation' => env('SYSTEM_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('SYSTEM_MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'managed_legacy' => [
            // Pricelist's own HostForge Managed MySQL database. Keep this
            // separate for app-local sessions/cache and one-time migration of
            // customer portal rows that were accidentally written here.
            'driver' => env('DB_CONNECTION', 'mysql'),
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'masterlist' => [
            // W68_MASTERLIST_HOSTFORGE_FALLBACK_20261019
            // core4_masterlist and core4_system_proposal are sibling schemas on
            // the same W68 MySQL service. Keep dedicated MASTERLIST_* values
            // authoritative, but inherit the working SYSTEM_* endpoint when a
            // HostForge reprovision drops the masterlist-specific variables.
            'driver' => env('MASTERLIST_DB_CONNECTION', env('SYSTEM_DB_CONNECTION', 'mysql')),
            'url' => env('MASTERLIST_DB_URL'),
            'host' => env('MASTERLIST_DB_HOST', env('SYSTEM_DB_HOST', env('DB_HOST', '127.0.0.1'))),
            'port' => env('MASTERLIST_DB_PORT', env('SYSTEM_DB_PORT', env('DB_PORT', '3306'))),
            'database' => env('MASTERLIST_DB_DATABASE', 'core4_masterlist'),
            'username' => env('MASTERLIST_DB_USERNAME', env('SYSTEM_DB_USERNAME', env('DB_USERNAME', 'root'))),
            'password' => env('MASTERLIST_DB_PASSWORD', env('SYSTEM_DB_PASSWORD', env('DB_PASSWORD', ''))),
            'unix_socket' => env('MASTERLIST_DB_SOCKET', ''),
            'charset' => env('MASTERLIST_DB_CHARSET', 'utf8mb4'),
            'collation' => env('MASTERLIST_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MASTERLIST_MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'sales_order' => [
            // W68_SALES_ORDER_HOSTFORGE_FALLBACK_20261019
            // Production keeps the core4_* schemas on the same MySQL service.
            // If dedicated sales-order credentials are not configured, inherit
            // the already-working Masterlist server credentials rather than
            // falling back to the local-development 127.0.0.1/root defaults.
            'driver' => env('SALES_ORDER_DB_CONNECTION', env('MASTERLIST_DB_CONNECTION', 'mysql')),
            'url' => env('SALES_ORDER_DB_URL'),
            'host' => env(
                'DB_SALES_HOST',
                env('SALES_ORDER_DB_HOST', env('MASTERLIST_DB_HOST', env('DB_HOST', '127.0.0.1')))
            ),
            'port' => env(
                'DB_SALES_PORT',
                env('SALES_ORDER_DB_PORT', env('MASTERLIST_DB_PORT', env('DB_PORT', '3306')))
            ),
            'database' => env('DB_SALES_DATABASE', env('SALES_ORDER_DB_DATABASE', 'core4_sales_order')),
            'username' => env(
                'DB_SALES_USERNAME',
                env('SALES_ORDER_DB_USERNAME', env('MASTERLIST_DB_USERNAME', env('DB_USERNAME', 'root')))
            ),
            'password' => env(
                'DB_SALES_PASSWORD',
                env('SALES_ORDER_DB_PASSWORD', env('MASTERLIST_DB_PASSWORD', env('DB_PASSWORD', '')))
            ),
            'unix_socket' => env('SALES_ORDER_DB_SOCKET', ''),
            'charset' => env('SALES_ORDER_DB_CHARSET', 'utf8mb4'),
            'collation' => env('SALES_ORDER_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('SALES_ORDER_MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'purchase' => [
            // W68_PURCHASE_DB_CONNECTION_20261024
            // Dedicated core4_purchase connection used by the customer Home
            // NEW ARRIVAL section. HostForge variables are DB_PURCHASE_*.
            'driver' => env('PURCHASE_DB_CONNECTION', 'mysql'),
            'url' => env('DB_PURCHASE_URL', env('PURCHASE_DB_URL')),
            'host' => env('DB_PURCHASE_HOST', env('PURCHASE_DB_HOST', '127.0.0.1')),
            'port' => env('DB_PURCHASE_PORT', env('PURCHASE_DB_PORT', '3306')),
            'database' => env('DB_PURCHASE_DATABASE', env('PURCHASE_DB_DATABASE', 'core4_purchase')),
            'username' => env('DB_PURCHASE_USERNAME', env('PURCHASE_DB_USERNAME', 'root')),
            'password' => env('DB_PURCHASE_PASSWORD', env('PURCHASE_DB_PASSWORD', '')),
            'unix_socket' => env('PURCHASE_DB_SOCKET', ''),
            'charset' => env('PURCHASE_DB_CHARSET', 'utf8mb4'),
            'collation' => env('PURCHASE_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('PURCHASE_MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'ledger' => [
            'driver' => env('LEDGER_DB_CONNECTION', 'mysql'),
            'url' => env('LEDGER_DB_URL'),
            'host' => env('LEDGER_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('LEDGER_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('LEDGER_DB_DATABASE', 'core4_ledger'),
            'username' => env('LEDGER_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('LEDGER_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('LEDGER_DB_SOCKET', ''),
            'charset' => env('LEDGER_DB_CHARSET', 'utf8mb4'),
            'collation' => env('LEDGER_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('LEDGER_MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
