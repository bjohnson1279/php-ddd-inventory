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

$driver = getenv('DB_CONNECTION') ?: 'pgsql';

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
        'driver'   => $driver,
        'host'     => getenv('DB_HOST') ?: 'db',
        'port'     => getenv('DB_PORT') ?: '5432',
        'database' => getenv('DB_DATABASE') ?: 'ddd_inventory',
        'username' => getenv('DB_USERNAME') ?: 'ddd_user',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        'charset'  => 'utf8',
        'schema'   => 'public',
}

$capsule->setAsGlobal();
$capsule->bootEloquent();

    require_once __DIR__ . '/sqlite_setup.php';
    \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema($capsule->getConnection());
}

// Now Illuminate\Database facade and DB can be used in repositories
return $capsule;


}

}

if ($driver === 'pgsql') {
    if (!extension_loaded('pdo_pgsql')) {
        $driver = 'sqlite';
    } else {
        $pgHost = getenv('DB_HOST') ?: 'db';
        $pgPort = (int)(getenv('DB_PORT') ?: 5432);
        $fp = @fsockopen($pgHost, $pgPort, $errno, $errstr, 0.1);
        if (!$fp) {
            $driver = 'sqlite';
        } else {
            fclose($fp);
        }
    }
}

    putenv('DB_CONNECTION=sqlite');
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_SERVER['DB_CONNECTION'] = 'sqlite';
}


    }
    if ($dbPath !== ':memory:') {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!file_exists($dbPath)) {
            @touch($dbPath);
        }
    }
}


    try {
        $capsule->getConnection()->statement('PRAGMA journal_mode=WAL;');
        $capsule->getConnection()->statement('PRAGMA busy_timeout=10000;');
    } catch (\Exception $e) {}
}

