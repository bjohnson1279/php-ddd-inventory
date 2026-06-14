<?php

require __DIR__ . '/../vendor/autoload.php';

// ── Global exception / error handler ─────────────────────────────────────────
// Must be registered before anything else can throw so that all unhandled
// exceptions return JSON instead of an HTML error page.
set_exception_handler(function (Throwable $e): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    error_log('[UNHANDLED] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['error' => 'Internal server error']);
    exit;
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false; // Respect the @ operator
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

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
use InventoryApp\Application\Catalog\Listeners\SyncCatalogToShopify;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\StockDecremented;
use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;

$dispatcher = ServiceContainer::dispatcher();
$syncClient = new ShopifyInventorySync(
    getenv('SHOPIFY_STORE_DOMAIN') ?: '',
    getenv('SHOPIFY_ACCESS_TOKEN') ?: ''
);
$mappingRepo = new ShopifyMappingRepository();

$shopifyListener = new SyncStockToShopify($syncClient, $mappingRepo, ServiceContainer::productRepo('system'));
$dispatcher->subscribe(StockReceived::class,   [$shopifyListener, 'handle']);
$dispatcher->subscribe(StockDecremented::class, [$shopifyListener, 'handle']);

$catalogSyncListener = new SyncCatalogToShopify($syncClient, $mappingRepo);
$dispatcher->subscribe(VariantAddedToCatalog::class, [$catalogSyncListener, 'handle']);

// Register QuickBooks Journal Entry Sync Listener
$qboSyncClient = new \InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksJournalSync(
    getenv('QUICKBOOKS_COMPANY_ID') ?: 'mock-company',
    getenv('QUICKBOOKS_ACCESS_TOKEN') ?: 'mock-token'
);
$qboMappingRepo = new \InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksMappingRepository();
$qboListener = new \InventoryApp\Application\Accounting\Listeners\SyncJournalToQuickBooks($qboSyncClient, $qboMappingRepo);
$dispatcher->subscribe(\InventoryApp\Domain\Accounting\Events\JournalEntryRecorded::class, [$qboListener, 'handle']);

// Register Xero Journal Entry Sync Listener
$xeroSyncClient = new \InventoryApp\Infrastructure\Integration\Xero\XeroJournalSync(
    getenv('XERO_TENANT_ID') ?: 'mock-tenant',
    getenv('XERO_ACCESS_TOKEN') ?: 'mock-token'
);
$xeroMappingRepo = new \InventoryApp\Infrastructure\Integration\Xero\XeroMappingRepository();
$xeroListener = new \InventoryApp\Application\Accounting\Listeners\SyncJournalToXero($xeroSyncClient, $xeroMappingRepo);
$dispatcher->subscribe(\InventoryApp\Domain\Accounting\Events\JournalEntryRecorded::class, [$xeroListener, 'handle']);

// Register NetSuite Journal Entry Sync Listener
$nsSyncClient = new \InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteJournalSync(
    getenv('NETSUITE_ACCOUNT_ID') ?: 'mock-account',
    getenv('NETSUITE_TOKEN') ?: 'mock-token'
);
$nsMappingRepo = new \InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteMappingRepository();
$nsListener = new \InventoryApp\Application\Accounting\Listeners\SyncJournalToNetSuite($nsSyncClient, $nsMappingRepo);
$dispatcher->subscribe(\InventoryApp\Domain\Accounting\Events\JournalEntryRecorded::class, [$nsListener, 'handle']);

$registerProductUseCase = new \InventoryApp\Application\Inventory\UseCases\RegisterProduct(
    ServiceContainer::productRepo('system'),
    $dispatcher
);
$createInventoryListener = new CreateInventoryItemOnVariantAdded($registerProductUseCase);
$dispatcher->subscribe(VariantAddedToCatalog::class, [$createInventoryListener, 'handle']);

// Register Realtime Notification Listener
$notificationService = new \InventoryApp\Application\Notification\Services\NotificationService();
$notificationListener = new \InventoryApp\Application\Notification\Listeners\NotificationListener($notificationService);
$dispatcher->subscribe(\InventoryApp\Domain\Inventory\Events\LowStockDetected::class, [$notificationListener, 'handleLowStock']);
$dispatcher->subscribe(\InventoryApp\Domain\Inventory\Events\StockReceived::class, [$notificationListener, 'handleStockReceived']);
$dispatcher->subscribe(\InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted::class, [$notificationListener, 'handleOnboardingSubmitted']);
$dispatcher->subscribe(\InventoryApp\Domain\Inventory\Events\StockReconciled::class, [$notificationListener, 'handleStockReconciled']);
$dispatcher->subscribe(\InventoryApp\Domain\Inventory\Events\OpeningBalancePosted::class, [$notificationListener, 'handleOpeningBalancePosted']);

// Register Cost Layer Creation Listener
$costLayerListener = new \InventoryApp\Application\Inventory\Listeners\CreateCostLayerListener();
$dispatcher->subscribe(StockReceived::class, [$costLayerListener, 'handleStockReceived']);
$dispatcher->subscribe(\InventoryApp\Domain\Inventory\Events\OpeningBalancePosted::class, [$costLayerListener, 'handleOpeningBalancePosted']);

// ── Request parsing ───────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

header('Content-Type: application/json');

// ── Request adapter ───────────────────────────────────────────────────────────
use InventoryApp\Infrastructure\Http\RequestInterface;

class ValidationException extends \Exception {}

class RequestAdapter implements RequestInterface
{
    private array $body;
    private array $query;

    public function __construct()
    {
        $testBody = $GLOBALS['__TEST_BODY__'] ?? null;
        $this->body  = is_array($testBody) ? $testBody : (json_decode(file_get_contents('php://input'), true) ?: []);
        $this->query = $_GET;
    }

    public function validate(array $rules): array
    {
        foreach ($rules as $key => $rule) {
            $this->validateField($key, $rule);
        }
        return $this->body;
    }

    private function validateField(string $key, string $rule): void
    {
        $parts = explode('|', $rule);

        if (in_array('required', $parts) && !isset($this->body[$key])) {
            throw new ValidationException("Validation failed: {$key} is required");
        }

        if (!isset($this->body[$key])) {
            return;
        }

        if (in_array('integer', $parts)) {
            $this->validateInteger($key);
        }

        if (in_array('min:1', $parts)) {
            $this->validateMinOne($key);
        }
    }

    private function validateInteger(string $key): void
    {
        $value = $this->body[$key];
        if (is_int($value)) {
            return;
        }
        if (!ctype_digit(strval($value))) {
            throw new ValidationException("Validation failed: {$key} must be integer");
        }
        $this->body[$key] = (int) $value;
    }

    private function validateMinOne(string $key): void
    {
        if ((int) $this->body[$key] < 1) {
            throw new ValidationException("Validation failed: {$key} must be at least 1");
        }
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
    $headers    = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($GLOBALS['__TEST_AUTH_HEADER__'] ?? '');
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
use InventoryApp\Infrastructure\Http\Controllers\BarcodeController;
use InventoryApp\Infrastructure\Http\Controllers\SerializedItemController;
use InventoryApp\Infrastructure\Http\Controllers\StockOnboardingController;
use InventoryApp\Infrastructure\Http\Controllers\JournalController;
use InventoryApp\Infrastructure\Http\Controllers\UomController;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Application\Identity\UseCases\RegisterUser;
use InventoryApp\Application\Identity\UseCases\AuthenticateUser;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\TransferStock;
use InventoryApp\Application\Inventory\UseCases\ProcessSale;
use InventoryApp\Application\Inventory\UseCases\ProcessReturn;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use InventoryApp\Application\Inventory\UseCases\StartInventoryCount;
use InventoryApp\Application\Inventory\UseCases\RecordCountItem;
use InventoryApp\Application\Inventory\UseCases\CompleteInventoryCount;
use InventoryApp\Application\Identity\UseCases\AssignRoleToUser;

$request = new RequestAdapter();

// ── Route: GET /api/notifications/subscribe (Server-Sent Events) ──────────────
if ($method === 'GET' && $uri === '/api/notifications/subscribe') {
    $token = $request->query('token');
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Missing auth token']);
        exit;
    }
    $tokenData = (new \InventoryApp\Infrastructure\Identity\ApiTokenService())->validate($token);
    if ($tokenData === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid token']);
        exit;
    }

    $tenantId = $tokenData->tenant_id;

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    echo "event: connected\ndata: " . json_encode(['message' => 'Subscribed to notifications']) . "\n\n";
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    $lastChecked = date('Y-m-d H:i:s');
    $sentIds = [];

    $start = time();
    while (time() - $start < 50) {
        if (connection_aborted()) {
            break;
        }

        $notifications = \InventoryApp\Infrastructure\Models\NotificationModel::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $lastChecked)
            ->get();

        foreach ($notifications as $n) {
            if (in_array($n->id, $sentIds)) {
                continue;
            }
            echo "event: notification\ndata: " . json_encode($n->toArray()) . "\n\n";
            $sentIds[] = $n->id;
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        sleep(1);
    }
    exit;
}

// ── Route: GET /api/notifications ─────────────────────────────────────────────
if ($method === 'GET' && $uri === '/api/notifications') {
    requireAuth();
    $response = (new \InventoryApp\Infrastructure\Http\Controllers\NotificationController())->list($request, tenantId());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/notifications/read-all ──────────────────────────────────
if ($method === 'POST' && $uri === '/api/notifications/read-all') {
    requireAuth();
    $response = (new \InventoryApp\Infrastructure\Http\Controllers\NotificationController())->readAll($request, tenantId());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/notifications/{id}/read ──────────────────────────────────
if ($method === 'POST' && preg_match('#^/api/notifications/([^/]+)/read$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new \InventoryApp\Infrastructure\Http\Controllers\NotificationController())->read($request, tenantId(), $id);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

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
            $role = !empty($roles) ? $roles[0] : 'staff';
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
        $tenantId        = tenantId();
        $userId          = \Ramsey\Uuid\Uuid::uuid4()->toString();
        // Generate a secure random temporary password (returned once to the caller).
        // The invited user must change it on first login.
        $temporaryPassword = bin2hex(random_bytes(12)); // 24 hex chars
        $name            = explode('@', $email)[0];

        $useCase = new RegisterUser(ServiceContainer::userRepo(), $dispatcher);
        $useCase->execute($userId, $tenantId, $email, $temporaryPassword, $name);

        http_response_code(201);
        echo json_encode([
            'message'            => 'User invited successfully.',
            'user_id'            => $userId,
            'temporary_password' => $temporaryPassword,
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Route: POST /auth/login ───────────────────────────────────────────────────
if ($method === 'POST' && ($uri === '/auth/login' || $uri === '/api/auth/login')) {
    $middleware = new \InventoryApp\Infrastructure\Http\Middleware\RateLimitMiddleware(5, 60);
    $response = $middleware->handle($request, function ($req) {
        $useCase  = new AuthenticateUser(ServiceContainer::userRepo(), new ApiTokenService());
        return (new AuthController())->login($req, $useCase);
    });

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

// ── Route: GET /api/catalog/products ─────────────────────────────────────────
if ($method === 'GET' && $uri === '/api/catalog/products') {
    requireAuth();
    try {
        $products = Capsule::table('catalog_products')->get()->toArray();
        $variants = Capsule::table('catalog_variants')->get()->toArray();
        
        $productsMap = [];
        foreach ($products as $p) {
            $pData = (array)$p;
            $pData['variants'] = [];
            $productsMap[$p->id] = $pData;
        }
        
        foreach ($variants as $v) {
            $vData = (array)$v;
            $vData['attributes'] = json_decode($v->attributes, true) ?: [];
            $vData['price'] = (float)$v->price;
            if (isset($productsMap[$v->product_id])) {
                $productsMap[$v->product_id]['variants'][] = $vData;
            }
        }
        
        http_response_code(200);
        echo json_encode(['products' => array_values($productsMap)]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
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

// ── Barcode Registry ──────────────────────────────────────────────────────────
// Route: GET /api/barcodes/lookup
if ($method === 'GET' && $uri === '/api/barcodes/lookup') {
    $response = (new BarcodeController())->lookup($request, ServiceContainer::barcodeRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/barcodes/assign
if ($method === 'POST' && $uri === '/api/barcodes/assign') {
    requireAuth();
    $response = (new BarcodeController())->assign($request, ServiceContainer::barcodeRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/barcodes/variants/{variantId}
if ($method === 'GET' && preg_match('#^/api/barcodes/variants/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $variantId = urldecode($m[1]);
    $response = (new BarcodeController())->getVariantSet($request, $variantId, ServiceContainer::barcodeRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Serial Number Tracking ─────────────────────────────────────────────────────
// Route: POST /api/serials
if ($method === 'POST' && $uri === '/api/serials') {
    requireAuth();
    $service = new \InventoryApp\Domain\Serial\Services\SerializedInventoryService(
        ServiceContainer::serializedRepo(),
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new SerializedItemController())->register($request, $service);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/serials
if ($method === 'GET' && $uri === '/api/serials') {
    requireAuth();
    try {
        $serials = Capsule::table('serialized_items')
            ->where('tenant_id', tenantId())
            ->get()
            ->toArray();
        $items = array_map(function ($s) {
            $data = (array)$s;
            $data['history'] = json_decode($s->history, true) ?: [];
            return $data;
        }, $serials);
        http_response_code(200);
        echo json_encode(['items' => $items]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Route: POST /api/serials/{id}/receive
if ($method === 'POST' && preg_match('#^/api/serials/([^/]+)/receive$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $service = new \InventoryApp\Domain\Serial\Services\SerializedInventoryService(
        ServiceContainer::serializedRepo(),
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new SerializedItemController())->receive($request, $id, $service, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/serials/{id}/sell
if ($method === 'POST' && preg_match('#^/api/serials/([^/]+)/sell$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $service = new \InventoryApp\Domain\Serial\Services\SerializedInventoryService(
        ServiceContainer::serializedRepo(),
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new SerializedItemController())->sell($request, $id, $service, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/serials/{id}/return
if ($method === 'POST' && preg_match('#^/api/serials/([^/]+)/return$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $service = new \InventoryApp\Domain\Serial\Services\SerializedInventoryService(
        ServiceContainer::serializedRepo(),
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new SerializedItemController())->acceptReturn($request, $id, $service, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/serials/{id}/restock
if ($method === 'POST' && preg_match('#^/api/serials/([^/]+)/restock$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $service = new \InventoryApp\Domain\Serial\Services\SerializedInventoryService(
        ServiceContainer::serializedRepo(),
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new SerializedItemController())->restock($request, $id, $service, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/serials/{id}/write-off
if ($method === 'POST' && preg_match('#^/api/serials/([^/]+)/write-off$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $service = new \InventoryApp\Domain\Serial\Services\SerializedInventoryService(
        ServiceContainer::serializedRepo(),
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new SerializedItemController())->writeOff($request, $id, $service, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/serials/lookup
if ($method === 'GET' && $uri === '/api/serials/lookup') {
    requireAuth();
    $response = (new SerializedItemController())->lookup($request, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/serials/variants/{variantId}
if ($method === 'GET' && preg_match('#^/api/serials/variants/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $variantId = urldecode($m[1]);
    $response = (new SerializedItemController())->listByVariant($request, $variantId, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/serials/variants/{variantId}/count
if ($method === 'GET' && preg_match('#^/api/serials/variants/([^/]+)/count$#', $uri, $m)) {
    requireAuth();
    $variantId = urldecode($m[1]);
    $response = (new SerializedItemController())->countByStatus($request, $variantId, ServiceContainer::serializedRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Opening Balances (Stock Onboarding) ───────────────────────────────────────
// Route: POST /api/onboardings
if ($method === 'POST' && $uri === '/api/onboardings') {
    requireAuth();
    $response = (new StockOnboardingController())->create($request, ServiceContainer::stockOnboardingRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/onboardings
if ($method === 'GET' && $uri === '/api/onboardings') {
    requireAuth();
    try {
        $onboardings = Capsule::table('stock_onboardings')
            ->where('tenant_id', tenantId())
            ->get()
            ->toArray();
        http_response_code(200);
        echo json_encode(['onboardings' => $onboardings]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Route: POST /api/onboardings/{id}/items
if ($method === 'POST' && preg_match('#^/api/onboardings/([^/]+)/items$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new StockOnboardingController())->setItem($request, $id, ServiceContainer::stockOnboardingRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: DELETE /api/onboardings/{id}/items/{variantId}
if ($method === 'DELETE' && preg_match('#^/api/onboardings/([^/]+)/items/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $variantId = urldecode($m[2]);
    $response = (new StockOnboardingController())->removeItem($request, $id, $variantId, ServiceContainer::stockOnboardingRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/onboardings/{id}/submit
if ($method === 'POST' && preg_match('#^/api/onboardings/([^/]+)/submit$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $service = new \InventoryApp\Domain\Inventory\Services\OpeningBalanceService(
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new StockOnboardingController())->submit($request, $id, $service, ServiceContainer::stockOnboardingRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/onboardings/{id}
if ($method === 'GET' && preg_match('#^/api/onboardings/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new StockOnboardingController())->show($request, $id, ServiceContainer::stockOnboardingRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Accounting Journal ────────────────────────────────────────────────────────
// Route: POST /api/journal/entries
if ($method === 'POST' && $uri === '/api/journal/entries') {
    requireAuth();
    $response = (new JournalController())->record($request, ServiceContainer::journalRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/journal/entries
if ($method === 'GET' && $uri === '/api/journal/entries') {
    requireAuth();
    $response = (new JournalController())->list($request, ServiceContainer::journalRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/reports/valuation
if ($method === 'GET' && $uri === '/api/reports/valuation') {
    requireAuth();
    $response = (new \InventoryApp\Infrastructure\Http\Controllers\ReportController())->valuation($request, tenantId());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Unit of Measure (UoM) ────────────────────────────────────────────────────
// Route: POST /api/uom/configurations
if ($method === 'POST' && $uri === '/api/uom/configurations') {
    requireAuth();
    $response = (new UomController())->create($request, ServiceContainer::uomConfigRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/uom/configurations/{id}/rules
if ($method === 'POST' && preg_match('#^/api/uom/configurations/([^/]+)/rules$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new UomController())->addRule($request, $id, ServiceContainer::uomConfigRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: DELETE /api/uom/configurations/{id}/rules
if ($method === 'DELETE' && preg_match('#^/api/uom/configurations/([^/]+)/rules$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new UomController())->removeRule($request, $id, ServiceContainer::uomConfigRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/uom/configurations/{id}/units
if ($method === 'POST' && preg_match('#^/api/uom/configurations/([^/]+)/units$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new UomController())->setUnits($request, $id, ServiceContainer::uomConfigRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/uom/configurations/variants/{variantId}
if ($method === 'GET' && preg_match('#^/api/uom/configurations/variants/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $variantId = urldecode($m[1]);
    $response = (new UomController())->show($request, $variantId, ServiceContainer::uomConfigRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/uom/configurations/{id}
if ($method === 'GET' && preg_match('#^/api/uom/configurations/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new UomController())->showById($request, $id, ServiceContainer::uomConfigRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Kitting & Bundles ────────────────────────────────────────────────────────
// Route: POST /api/kits
if ($method === 'POST' && $uri === '/api/kits') {
    requireAuth();
    $response = (new KitController())->create($request, ServiceContainer::kitRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/kits
if ($method === 'GET' && $uri === '/api/kits') {
    requireAuth();
    try {
        $kits = Capsule::table('kits')->get()->toArray();
        $components = Capsule::table('kit_components')->get()->toArray();
        
        $kitsMap = [];
        foreach ($kits as $k) {
            $kData = (array)$k;
            $kData['components'] = [];
            $kitsMap[$k->id] = $kData;
        }
        
        foreach ($components as $c) {
            $cData = (array)$c;
            if (isset($kitsMap[$c->kit_id])) {
                $kitsMap[$c->kit_id]['components'][] = $cData;
            }
        }
        
        http_response_code(200);
        echo json_encode(['kits' => array_values($kitsMap)]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Route: POST /api/kits/{id}/components
if ($method === 'POST' && preg_match('#^/api/kits/([^/]+)/components$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new KitController())->addComponent($request, $id, ServiceContainer::kitRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/kits/{id}
if ($method === 'GET' && preg_match('#^/api/kits/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $response = (new KitController())->show($request, $id, ServiceContainer::kitRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: GET /api/kits/sku/{sku}
if ($method === 'GET' && preg_match('#^/api/kits/sku/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $sku = urldecode($m[1]);
    $response = (new KitController())->showBySku($request, $sku, ServiceContainer::kitRepo());
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// Route: POST /api/kits/{id}/sell
if ($method === 'POST' && preg_match('#^/api/kits/([^/]+)/sell$#', $uri, $m)) {
    requireAuth();
    $id = urldecode($m[1]);
    $inventoryService = new \InventoryApp\Domain\Inventory\Services\InventoryService(
        ServiceContainer::ledgerRepo(tenantId()),
        $dispatcher
    );
    $response = (new KitController())->sell($request, $id, ServiceContainer::kitRepo(), $inventoryService);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/webhooks/shopify ────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/webhooks/shopify') {
    // Note: Do not call requireAuth() here because Shopify uses HMAC signature headers instead of API bearer tokens
    $response = (new \InventoryApp\Infrastructure\Http\Controllers\ShopifyWebhookController())->handle($request);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
    exit;
}

// ── Route: POST /api/inventory/sale ──────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/inventory/sale') {
    requireAuth();
    $body      = json_decode(file_get_contents('php://input'), true) ?: [];
    $sku       = $body['sku']         ?? '';
    $locationId = $body['location_id'] ?? '';
    $quantity  = (int)($body['quantity']  ?? 0);
    $orderId   = $body['order_id']    ?? null;

    if (!$sku || !$locationId || $quantity < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'sku, location_id, and quantity (min 1) are required']);
        exit;
    }

    try {
        $useCase = new ProcessSale(ServiceContainer::productRepo(tenantId()), $dispatcher);
        $useCase->execute($sku, $locationId, $quantity, $orderId);
        http_response_code(200);
        echo json_encode(['message' => 'Sale processed successfully']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Route: POST /api/inventory/return ────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/inventory/return') {
    requireAuth();
    $body       = json_decode(file_get_contents('php://input'), true) ?: [];
    $sku        = $body['sku']         ?? '';
    $locationId = $body['location_id'] ?? '';
    $quantity   = (int)($body['quantity']   ?? 0);
    $condition  = $body['condition']   ?? 'new';
    $orderId    = $body['order_id']    ?? null;

    if (!$sku || !$locationId || $quantity < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'sku, location_id, and quantity (min 1) are required']);
        exit;
    }

    try {
        $useCase = new ProcessReturn(ServiceContainer::productRepo(tenantId()), $dispatcher);
        $useCase->execute($sku, $locationId, $quantity, $condition, $orderId);
        http_response_code(200);
        echo json_encode(['message' => 'Return processed successfully']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Route: GET /api/inventory/counts/{id} ────────────────────────────────────
if ($method === 'GET' && preg_match('#^/api/inventory/counts/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $countId = urldecode($m[1]);

    try {
        $count = ServiceContainer::inventoryCountRepo(tenantId())->findById($countId);
        if (!$count) {
            http_response_code(404);
            echo json_encode(['error' => 'Inventory count not found']);
            exit;
        }
        $items = array_map(fn($item) => [
            'sku'      => $item->getSku()->getValue(),
            'quantity' => $item->getCountedQuantity()->getValue(),
        ], $count->getItems());

        http_response_code(200);
        echo json_encode([
            'id'     => $count->getId(),
            'status' => $count->getStatus()->getValue(),
            'items'  => $items,
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Route: PATCH /api/users/{id}/role ────────────────────────────────────────
if ($method === 'PATCH' && preg_match('#^/api/users/([^/]+)/role$#', $uri, $m)) {
    requireAuth();
    $targetUserId = urldecode($m[1]);
    $actingUserId = $_SERVER['auth.user_id'] ?? '';
    $body         = json_decode(file_get_contents('php://input'), true) ?: [];
    $roleSlug     = $body['role'] ?? '';

    if (!$roleSlug) {
        http_response_code(400);
        echo json_encode(['error' => 'role is required (admin|manager|staff)']);
        exit;
    }

    try {
        $useCase = new AssignRoleToUser(ServiceContainer::userRepo());
        $useCase->execute($targetUserId, $roleSlug, $actingUserId);
        http_response_code(200);
        echo json_encode(['message' => "Role '{$roleSlug}' assigned successfully"]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Shopify Webhooks ──────────────────────────────────────────────────────────
// The raw body MUST be read before any JSON decoding; the HMAC is computed over
// the exact bytes Shopify sent, not the re-encoded JSON.

if ($method === 'POST' && str_starts_with($uri, '/webhooks/shopify/')) {
    $rawBody    = file_get_contents('php://input');
    $hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
    $secret     = getenv('SHOPIFY_WEBHOOK_SECRET') ?: '';

    $verifier = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyWebhookVerifier($secret);

    if (!$verifier->verify($rawBody, $hmacHeader)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: invalid HMAC signature']);
        exit;
    }

    $payload = json_decode($rawBody, true) ?: [];

    $mapper = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyOrderMapper(
        new \InventoryApp\Application\Inventory\UseCases\ProcessSaleBatch(
            ServiceContainer::productRepo('system'), $dispatcher
        ),
        new \InventoryApp\Application\Inventory\UseCases\ProcessReturnBatch(
            ServiceContainer::productRepo('system'), $dispatcher
        ),
        new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository()
    );

    // ── POST /webhooks/shopify/orders/paid ────────────────────────────────────
    if ($uri === '/webhooks/shopify/orders/paid') {
        try {
            $mapper->handleOrderPaid($payload);
        } catch (\Exception $e) {
            // Log but always return 200 — Shopify retries on non-200 responses,
            // which could cause duplicate stock decrements on transient errors.
            error_log('[Shopify webhook] orders/paid error: ' . $e->getMessage());
        }
        http_response_code(200);
        echo json_encode(['message' => 'Accepted']);
        exit;
    }

    // ── POST /webhooks/shopify/refunds/create ─────────────────────────────────
    if ($uri === '/webhooks/shopify/refunds/create') {
        try {
            $mapper->handleRefundCreated($payload);
        } catch (\Exception $e) {
            error_log('[Shopify webhook] refunds/create error: ' . $e->getMessage());
        }
        http_response_code(200);
        echo json_encode(['message' => 'Accepted']);
        exit;
    }

    // Unknown Shopify topic — still verified, just not handled yet
    http_response_code(200);
    echo json_encode(['message' => 'Webhook topic not handled']);
    exit;
}

// ── Fallback ──────────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['message' => 'DDD Inventory API is running', 'uri' => $uri]);
