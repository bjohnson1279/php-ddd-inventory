<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Load env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Boot Eloquent (Capsule)
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => getenv('DB_CONNECTION') ?: 'pgsql',
    'host' => getenv('DB_HOST') ?: 'db',
    'database' => getenv('DB_DATABASE') ?: 'ddd_inventory',
    'username' => getenv('DB_USERNAME') ?: 'ddd_user',
    'password' => getenv('DB_PASSWORD') ?: 'secret',
    'port' => getenv('DB_PORT') ?: 5432,
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$connection = $capsule->getConnection();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$body = json_decode(file_get_contents('php://input'), true) ?: [];

header('Content-Type: application/json');

// Delegate to controllers and use-cases via ServiceContainer
use InventoryApp\Infrastructure\ServiceContainer;
use InventoryApp\Infrastructure\Http\Controllers\CatalogController;
use InventoryApp\Infrastructure\Http\Controllers\InventoryController;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use Illuminate\Http\Request;

// POST /api/catalog/products -> create catalog product
if ($method === 'POST' && $uri === '/api/catalog/products') {
    $request = Request::createFromGlobals();
    $controller = new CatalogController();
    $useCase = new CreateProductCatalog(ServiceContainer::catalogProductRepo());
    $response = $controller->createProduct($request, $useCase);

    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// POST /api/inventory/receive -> record inventory transaction
if ($method === 'POST' && $uri === '/api/inventory/receive') {
    $request = Request::createFromGlobals();
    $controller = new InventoryController();
    $useCase = new ReceiveStock(ServiceContainer::productRepo());
    $response = $controller->receive($request, $useCase);

    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// GET /api/inventory/{sku}/stock -> return computed stock from use-case
if ($method === 'GET' && preg_match('#^/api/inventory/([^/]+)/stock$#', $uri, $m)) {
    $sku = urldecode($m[1]);
    $request = Request::createFromGlobals();
    $controller = new InventoryController();
    $useCase = new GetStockLevel(ServiceContainer::productRepo());
    $response = $controller->stockLevel($request, $sku, $useCase);

    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Fallback: simple status page
http_response_code(200);
echo json_encode(['message' => 'DDD Inventory API is running', 'uri' => $uri]);
