<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// Autoload if running standalone
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

// Load environment from project root .env if vlucas/phpdotenv is available
if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
    $dotenv->safeLoad();
}

if (!getenv('DB_CONNECTION') || (getenv('DB_CONNECTION') === 'pgsql' && !extension_loaded('pdo_pgsql'))) {
    putenv('DB_CONNECTION=sqlite');
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_SERVER['DB_CONNECTION'] = 'sqlite';
}
$driver = getenv('DB_CONNECTION') ?: 'sqlite';

$capsule = new Capsule();

if ($driver === 'sqlite') {
    $dbPath = getenv('DB_DATABASE') ?: ':memory:';
    // If it's a relative path, resolve it relative to the project root (4 levels up from this directory)
    if ($dbPath !== ':memory:' && !str_starts_with($dbPath, '/') && !str_contains($dbPath, ':')) {
        $dbPath = __DIR__ . '/../../../' . $dbPath;
    }
    $capsule->addConnection([
        'driver'   => 'sqlite',
        'database' => $dbPath,
        'prefix'   => '',
    ]);
} else {
    $capsule->addConnection([
        'driver'   => $driver,
        'host'     => getenv('DB_HOST') ?: 'db',
        'port'     => getenv('DB_PORT') ?: '5432',
        'database' => getenv('DB_DATABASE') ?: 'ddd_inventory',
        'username' => getenv('DB_USERNAME') ?: 'ddd_user',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        'charset'  => 'utf8',
        'prefix'   => '',
        'schema'   => 'public',
    ]);
}

$capsule->setAsGlobal();
$capsule->bootEloquent();

if ($driver === 'sqlite') {
    try {
        $capsule->getConnection()->statement('PRAGMA journal_mode=WAL;');
        $capsule->getConnection()->statement('PRAGMA busy_timeout=10000;');
    } catch (\Exception $e) {}
    require_once __DIR__ . '/sqlite_setup.php';
    \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema($capsule->getConnection());
}

// Now Illuminate\Database facade and DB can be used in repositories
return $capsule;
