<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

chdir(dirname(__DIR__));

define('LARAVEL_START', microtime(true));

if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || isset($_SERVER['VERCEL'])) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_SCHEME'] = 'https';
    $_SERVER['SERVER_PORT'] = '443';
}

function set_default_env(string $key, string $value): void
{
    if (getenv($key) !== false) {
        return;
    }

    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function neon_endpoint_id(): ?string
{
    foreach (['DB_URL', 'DATABASE_URL', 'POSTGRES_URL'] as $key) {
        $url = getenv($key);

        if ($url === false || $url === '') {
            continue;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && str_starts_with($host, 'ep-')) {
            return explode('.', $host, 2)[0];
        }
    }

    foreach (['DB_HOST', 'PGHOST', 'POSTGRES_HOST'] as $key) {
        $host = getenv($key);

        if (is_string($host) && str_starts_with($host, 'ep-')) {
            return explode('.', $host, 2)[0];
        }
    }

    return null;
}

$storagePath = '/tmp/laravel-storage';

foreach ([
    "{$storagePath}/framework/cache/data",
    "{$storagePath}/framework/sessions",
    "{$storagePath}/framework/testing",
    "{$storagePath}/framework/views",
    "{$storagePath}/logs",
] as $path) {
    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

set_default_env('APP_ENV', 'production');
set_default_env('APP_DEBUG', 'false');
set_default_env('APP_URL', 'https://'.($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost'));
set_default_env('BCRYPT_ROUNDS', '12');
set_default_env('CACHE_STORE', 'array');
set_default_env(
    'DB_CONNECTION',
    getenv('DB_URL') !== false || getenv('DATABASE_URL') !== false || getenv('POSTGRES_URL') !== false
        ? 'pgsql'
        : 'sqlite'
);
if (getenv('DB_CONNECTION') === 'sqlite') {
    set_default_env('DB_DATABASE', dirname(__DIR__).'/database/database.sqlite');
}
if ($endpointId = neon_endpoint_id()) {
    set_default_env('PGOPTIONS', "endpoint={$endpointId}");
}
set_default_env('LOG_CHANNEL', 'stderr');
set_default_env('QUEUE_CONNECTION', 'sync');
set_default_env('SESSION_DRIVER', 'cookie');
set_default_env('LARAVEL_STORAGE_PATH', $storagePath);

if (file_exists($maintenance = dirname(__DIR__).'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require dirname(__DIR__).'/vendor/autoload.php';

/** @var Application $app */
$app = require_once dirname(__DIR__).'/bootstrap/app.php';

$app->handleRequest(Request::capture());
