<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// Autoload if running standalone
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

// Load environment from project root .env if vlucas/phpdotenv is available
if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
    $dotenv->safeLoad();
}

$dbConfig = [
    'driver' => getenv('DB_CONNECTION') ?: 'pgsql',
    'host' => getenv('DB_HOST') ?: 'db',
    'port' => getenv('DB_PORT') ?: '5432',
    'database' => getenv('DB_DATABASE') ?: 'ddd_inventory',
    'username' => getenv('DB_USERNAME') ?: 'ddd_user',
    'password' => getenv('DB_PASSWORD') ?: 'secret',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
];

$capsule = new Capsule();
$capsule->addConnection($dbConfig);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Now Illuminate\Database facade and DB can be used in repositories
return $capsule;
