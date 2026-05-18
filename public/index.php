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

// POST /api/catalog/products -> create catalog product
if ($method === 'POST' && $uri === '/api/catalog/products') {
    if (empty($body['name']) || empty($body['description']) || empty($body['department'])) {
        http_response_code(400);
        echo json_encode(['error' => 'name, description and department are required']);
        exit;
    }

    $insertData = [
        'name' => $body['name'],
        'description' => $body['description'],
        'department' => $body['department'],
        'created_at' => date('c'),
    ];

    try {
        $id = $connection->table('catalog_products')->insertGetId($insertData, 'id');
        http_response_code(201);
        echo json_encode(['message' => 'Catalog product created successfully', 'id' => $id]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// POST /api/inventory/receive -> record inventory transaction
if ($method === 'POST' && $uri === '/api/inventory/receive') {
    if (empty($body['sku']) || empty($body['quantity']) || empty($body['location_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'sku, quantity and location_id are required']);
        exit;
    }

    $sku = $body['sku'];
    $qty = (int)$body['quantity'];
    $location = $body['location_id'];

    // Find product by SKU
    $product = $connection->table('products')->where('sku', $sku)->first();
    if (!$product) {
        http_response_code(400);
        echo json_encode(['error' => 'Product not found for SKU: ' . $sku]);
        exit;
    }

    try {
        $connection->table('inventory_transactions')->insert([
            'product_id' => $product->id,
            'type' => 'receive',
            'quantity_change' => $qty,
            'condition' => 'good',
            'created_at' => date('c'),
            'reference_id' => $body['reference_id'] ?? null,
        ]);

        http_response_code(200);
        echo json_encode(['message' => 'Stock received', 'sku' => $sku, 'quantity' => $qty]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// GET /api/inventory/{sku}/stock -> return computed stock from transactions
if ($method === 'GET' && preg_match('#^/api/inventory/([^/]+)/stock$#', $uri, $m)) {
    $sku = urldecode($m[1]);
    $product = $connection->table('products')->where('sku', $sku)->first();
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    $sum = $connection->table('inventory_transactions')->where('product_id', $product->id)->sum('quantity_change');
    $stock = (int)$sum;

    echo json_encode(['sku' => $sku, 'product_id' => $product->id, 'stock' => $stock]);
    exit;
}

// Fallback: simple status page
http_response_code(200);
echo json_encode(['message' => 'DDD Inventory API is running', 'uri' => $uri]);
