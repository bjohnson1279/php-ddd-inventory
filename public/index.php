<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// ── Environment ──────────────────────────────────────────────────────────────
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// ── Eloquent (Capsule) ────────────────────────────────────────────────────────
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => getenv('DB_CONNECTION') ?: 'pgsql',
    'host'      => getenv('DB_HOST')       ?: 'db',
    'database'  => getenv('DB_DATABASE')   ?: 'ddd_inventory',
    'username'  => getenv('DB_USERNAME')   ?: 'ddd_user',
    'password'  => getenv('DB_PASSWORD')   ?: 'secret',
    'port'      => getenv('DB_PORT')       ?: 5432,
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// ── Event listeners ──────────────────────────────────────────────────────────
use InventoryApp\Infrastructure\ServiceContainer;
use InventoryApp\Application\Inventory\Listeners\SyncStockToShopify;
use InventoryApp\Application\Inventory\Listeners\CreateInventoryItemOnVariantAdded;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\StockDecremented;
use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySyncClient;

$dispatcher = ServiceContainer::dispatcher();
$syncClient = new ShopifyInventorySyncClient(
    getenv('SHOPIFY_STORE_DOMAIN') ?: '',
    getenv('SHOPIFY_ACCESS_TOKEN') ?: ''
);

$dispatcher->subscribe(StockReceived::class,   new SyncStockToShopify($syncClient, ServiceContainer::barcodeRepo()));
$dispatcher->subscribe(StockDecremented::class, new SyncStockToShopify($syncClient, ServiceContainer::barcodeRepo()));
$dispatcher->subscribe(VariantAddedToCatalog::class, new CreateInventoryItemOnVariantAdded(ServiceContainer::productRepo(tenantId())));

// ── Request parsing ───────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

header('Content-Type: application/json');

// ── Request adapter ───────────────────────────────────────────────────────────
use InventoryApp\Infrastructure\Http\RequestInterface;

class RequestAdapter implements RequestInterface
{
    private array $body;
    private array $query;

    public function __construct()
    {
        $this->body  = json_decode(file_get_contents('php://input'), true) ?: [];
        $this->query = $_GET;
    }

    public function validate(array $rules): array
    {
        foreach ($rules as $key => $rule) {
            $parts = explode('|', $rule);
            if (in_array('required', $parts) && !isset($this->body[$key])) {
                throw new \Exception("Validation failed: {$key} is required");
            }
            if (isset($this->body[$key]) && in_array('integer', $parts)) {
                if (!is_int($this->body[$key])) {
                    if (!ctype_digit(strval($this->body[$key]))) {
                        throw new \Exception("Validation failed: {$key} must be integer");
                    }
                    $this->body[$key] = (int) $this->body[$key];
                }
            }
            if (isset($this->body[$key]) && in_array('min:1', $parts)) {
                if ((int) $this->body[$key] < 1) {
                    throw new \Exception("Validation failed: {$key} must be at least 1");
                }
            }
        }
        return $this->body;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }
}

// ── Auth middleware helper ────────────────────────────────────────────────────
use InventoryApp\Infrastructure\Identity\ApiTokenService;

function requireAuth(): void
{
    $headers    = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token      = null;

    if (str_starts_with($authHeader, 'Bearer ')) {
        $token = trim(substr($authHeader, 7));
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing or malformed Authorization header']);
        exit;
    }

    $tokenData = (new ApiTokenService())->validate($token);

    if ($tokenData === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }

    // Make the resolved identity available to the rest of the request
    $_SERVER['auth.user_id']   = $tokenData->user_id;
    $_SERVER['auth.tenant_id'] = $tokenData->tenant_id;
}

function tenantId(): string
{
    return $_SERVER['auth.tenant_id'] ?? 'system';
}


// ── Controllers & use-cases ───────────────────────────────────────────────────
use InventoryApp\Infrastructure\Http\Controllers\AuthController;
use InventoryApp\Infrastructure\Http\Controllers\CatalogController;
use InventoryApp\Infrastructure\Http\Controllers\InventoryController;
use InventoryApp\Infrastructure\Http\Controllers\InventoryCountController;
use InventoryApp\Application\Identity\UseCases\RegisterUser;
use InventoryApp\Application\Identity\UseCases\AuthenticateUser;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\TransferStock;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use InventoryApp\Application\Inventory\UseCases\StartInventoryCount;
use InventoryApp\Application\Inventory\UseCases\RecordCountItem;
use InventoryApp\Application\Inventory\UseCases\CompleteInventoryCount;

$request = new RequestAdapter();

// ── Route: POST /auth/register ────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/auth/register') {
    $useCase  = new RegisterUser(ServiceContainer::userRepo(), $dispatcher);
    $response = (new AuthController())->register($request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/setup ───────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/setup') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $orgName = $body['orgName'] ?? '';
    $tenantId = $body['tenantId'] ?? '';
    $adminName = $body['adminName'] ?? '';
    $adminEmail = $body['adminEmail'] ?? '';
    $adminPassword = $body['adminPassword'] ?? '';
    
    if (empty($orgName) || empty($tenantId) || empty($adminName) || empty($adminEmail) || empty($adminPassword)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields (orgName, tenantId, adminName, adminEmail, adminPassword) are required.']);
        exit;
    }
    
    try {
        // 1. Insert tenant
        Capsule::table('tenants')->insertOrIgnore([
            'id' => $tenantId,
            'name' => $orgName,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 2. Register admin user
        $userRepo = ServiceContainer::userRepo();
        $adminId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        
        $existing = $userRepo->findByEmail($adminEmail, new \InventoryApp\Domain\Identity\ValueObjects\TenantId($tenantId));
        if (!$existing) {
            $user = \InventoryApp\Domain\Identity\Entities\User::register(
                $adminId,
                new \InventoryApp\Domain\Identity\ValueObjects\TenantId($tenantId),
                $adminEmail,
                $adminPassword,
                $adminName
            );
            $user->assignRole(\InventoryApp\Domain\Identity\Entities\Role::createDefault('admin'));
            $userRepo->save($user);
        }
        
        http_response_code(200);
        echo json_encode(['message' => 'Organization and admin account set up successfully.']);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Route: GET /api/users ─────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '/api/users') {
    requireAuth();
    try {
        $userModels = \InventoryApp\Infrastructure\Models\UserModel::with('userRoles')
            ->where('tenant_id', tenantId())
            ->get();
            
        $users = $userModels->map(function($model) {
            $roles = $model->userRoles->pluck('id')->all();
            $role = count($roles) > 0 ? $roles[0] : 'staff';
            return [
                'id' => $model->id,
                'email' => $model->email,
                'role' => $role
            ];
        })->all();
        
        http_response_code(200);
        echo json_encode(['users' => $users]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Route: POST /api/users ────────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/users') {
    requireAuth();
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $email = $body['email'] ?? '';
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required.']);
        exit;
    }
    
    try {
        $tenantId = tenantId();
        $userId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $password = 'password123'; // Default password for invited users
        $name = explode('@', $email)[0];
        
        $useCase = new RegisterUser(ServiceContainer::userRepo(), $dispatcher);
        $useCase->execute($userId, $tenantId, $email, $password, $name);
        
        http_response_code(201);
        echo json_encode(['message' => 'User invited successfully.']);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Route: POST /auth/login ───────────────────────────────────────────────────
if ($method === 'POST' && ($uri === '/auth/login' || $uri === '/api/auth/login')) {
    $useCase  = new AuthenticateUser(ServiceContainer::userRepo(), new ApiTokenService());
    $response = (new AuthController())->login($request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/inventory/receive ───────────────────────────────────────
if ($method === 'POST' && $uri === '/api/inventory/receive') {
    requireAuth();
    $useCase  = new ReceiveStock(ServiceContainer::productRepo(tenantId()), $dispatcher);
    $response = (new InventoryController())->receive($request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/inventory/dispatch ──────────────────────────────────────
if ($method === 'POST' && $uri === '/api/inventory/dispatch') {
    requireAuth();
    $useCase  = new DispatchStock(ServiceContainer::productRepo(tenantId()), $dispatcher);
    $response = (new InventoryController())->dispatch($request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/inventory/transfer ──────────────────────────────────────
if ($method === 'POST' && $uri === '/api/inventory/transfer') {
    requireAuth();
    $useCase  = new TransferStock(ServiceContainer::productRepo(tenantId()), $dispatcher);
    $response = (new InventoryController())->transfer($request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: GET /api/inventory/{sku}/stock ────────────────────────────────────
if ($method === 'GET' && preg_match('#^/api/inventory/([^/]+)/stock$#', $uri, $m)) {
    requireAuth();
    $sku      = urldecode($m[1]);
    $useCase  = new GetStockLevel(ServiceContainer::productRepo(tenantId()));
    $response = (new InventoryController())->stockLevel($request, $sku, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/inventory/counts ────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/inventory/counts') {
    requireAuth();
    $useCase  = new StartInventoryCount(ServiceContainer::inventoryCountRepo(tenantId()));
    $response = (new InventoryCountController())->start($request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/inventory/counts/{id}/items ─────────────────────────────
if ($method === 'POST' && preg_match('#^/api/inventory/counts/([^/]+)/items$#', $uri, $m)) {
    requireAuth();
    $countId  = urldecode($m[1]);
    $useCase  = new RecordCountItem(ServiceContainer::inventoryCountRepo(tenantId()));
    $response = (new InventoryCountController())->recordItem($countId, $request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/inventory/counts/{id}/complete ──────────────────────────
if ($method === 'POST' && preg_match('#^/api/inventory/counts/([^/]+)/complete$#', $uri, $m)) {
    requireAuth();
    $countId  = urldecode($m[1]);
    $useCase  = new CompleteInventoryCount(
        ServiceContainer::inventoryCountRepo(tenantId()),
        ServiceContainer::productRepo(tenantId()),
        $dispatcher
    );
    $response = (new InventoryCountController())->complete($countId, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/catalog/products ────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/catalog/products') {
    requireAuth();
    $useCase  = new CreateProductCatalog(ServiceContainer::catalogProductRepo());
    $response = (new CatalogController())->createProduct($request, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/catalog/products/{id}/variants ──────────────────────────
if ($method === 'POST' && preg_match('#^/api/catalog/products/([^/]+)/variants$#', $uri, $m)) {
    requireAuth();
    $productId = urldecode($m[1]);
    $useCase   = new AddVariant(ServiceContainer::catalogProductRepo(), $dispatcher);
    $response  = (new CatalogController())->addVariant($request, $productId, $useCase);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Fallback ──────────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['message' => 'DDD Inventory API is running', 'uri' => $uri]);
