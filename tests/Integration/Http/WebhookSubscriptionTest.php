<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Infrastructure\Models\WebhookSubscriptionModel;
use InventoryApp\Infrastructure\Models\WebhookDeliveryModel;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class WebhookSubscriptionTest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        $output = [];
        $command = "php -S 127.0.0.1:8093 public/index.php > tests/Integration/Http/server_webhooks.log 2>&1 & echo $!";
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);
        

        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8093, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                break;
            }
            usleep(50000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pid) {
            exec("kill " . self::$pid . " > /dev/null 2>&1");
        }
    }

    protected function setUp(): void
    {
        Capsule::table('webhook_subscriptions')->delete();
        Capsule::table('webhook_deliveries')->delete();
        Capsule::table('users')->delete();
        Capsule::table('user_roles')->delete();
        Capsule::table('tenants')->where('id', '!=', 'test-tenant')->delete();

        $suffix = bin2hex(random_bytes(4));
        $this->tenantId = 'tenant-' . $suffix;
        $this->email = 'admin-' . $suffix . '@example.com';
        $this->password = 'SecurePassword123';

        $setupRes = $this->request('POST', '/api/setup', [
            'orgName'       => 'Test Org ' . $suffix,
            'tenantId'      => $this->tenantId,
            'adminName'     => 'Admin User',
            'adminEmail'    => $this->email,
            'adminPassword' => $this->password,
        ]);
        $this->assertEquals(200, $setupRes['status']);

        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => $this->email,
            'password'  => $this->password,
        $this->assertEquals(200, $loginRes['status']);
        $this->token = $loginRes['body']['token'];
    }

    public function testWebhookSubscriptionLifecycleAndWorker(): void
    {
        // 1. Create a webhook subscription
        $createRes = $this->request('POST', '/api/webhook-subscriptions', [
            'targetUrl' => 'https://example.com/webhook',
            'secret' => 'supersecret-key',
            'eventTypes' => ['StockUpdatedEvent']
        ], $this->token);

        $this->assertEquals(201, $createRes['status'], json_encode($createRes));
        $this->assertNotEmpty($createRes['body']['id']);
        $subId = $createRes['body']['id'];

        // 2. List webhook subscriptions
        $listRes = $this->request('GET', '/api/webhook-subscriptions', [], $this->token);
        $this->assertEquals(200, $listRes['status']);
        $this->assertCount(1, $listRes['body']);
        $this->assertEquals('https://example.com/webhook', $listRes['body'][0]['targetUrl']);

        // 3. Update subscription
        $updateRes = $this->request('PUT', '/api/webhook-subscriptions/' . $subId, [
            'targetUrl' => 'https://example.com/webhook-updated',
            'isActive' => false
        $this->assertEquals(200, $updateRes['status']);
        $this->assertEquals('https://example.com/webhook-updated', $updateRes['body']['targetUrl']);
        $this->assertFalse($updateRes['body']['isActive']);

        // 4. Delete subscription
        $deleteRes = $this->request('DELETE', '/api/webhook-subscriptions/' . $subId, [], $this->token);
        $this->assertEquals(204, $deleteRes['status']);

        // Verify it is gone
        $listRes2 = $this->request('GET', '/api/webhook-subscriptions', [], $this->token);
        $this->assertCount(0, $listRes2['body']);
    }

    public function testWebhookWorkerExecutesWithBackoff(): void
    {
        // 1. Manually insert a delivery and subscription to test webhook worker
        $subId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        Capsule::table('webhook_subscriptions')->insert([
            'id' => $subId,
            'target_url' => 'https://example.com/target',
            'secret' => 'sec',
            'event_types' => json_encode(['TestEvent']),
            'is_active' => true

        $deliveryId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        Capsule::table('webhook_deliveries')->insert([
            'id' => $deliveryId,
            'subscription_id' => $subId,
            'event_type' => 'TestEvent',
            'payload' => json_encode(['sku' => 'SKU-1']),
            'status' => 'Pending',
            'attempts' => 0,
            'next_attempt_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')

        // Run the CLI worker script with --once flag
        $worker = new \InventoryApp\Application\Webhooks\Workers\WebhookDeliveryWorker();
        $worker->run(true);
        $resultCode = -1;
        exec("php scripts/webhook-worker.php --once", $output, $resultCode);

        // It should try to send, fail (since internet domain target_url or mock), and increment attempt
        $delivery = Capsule::table('webhook_deliveries')->where('id', $deliveryId)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('Pending', $delivery->status);
        $this->assertEquals(1, $delivery->attempts);
        $this->assertNotEmpty($delivery->last_error);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8093' . $path;
        $options = [
            'http' => [
                'header'        => "Content-Type: application/json\r\n",
                'method'        => $method,
                'content'       => json_encode($body),
                'ignore_errors' => true,
            ]
        ];

        if ($token) {
            $options['http']['header'] .= "Authorization: Bearer {$token}\r\n";
        }

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        

        $statusCode = 500;
        if (isset($http_response_header) && isset($http_response_header[0])) {
            preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
            $statusCode = (int)$match[1];
        }

        $decoded = json_decode((string)$result, true);
        return [
            'status' => $statusCode,
            'body'   => (json_last_error() === JSON_ERROR_NONE) ? $decoded : $result
    }
}





{
    private static $serverProcess = null;

        public static function setUpBeforeClass(): void
    {
        $baseDir = realpath(__DIR__ . '/../../..');
        $dbPath = $baseDir . '/storage/data/test_webhooksubscriptiontest.sqlite';
        if (!file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            @touch($dbPath);
        }
        $extDir = 'C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext';
        $phpExec = PHP_BINARY . ' -d extension_dir="C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" -d extension=pdo -d extension=mbstring -d extension=pdo_sqlite';
        $cmd = $phpExec . ' -S 127.0.0.1:8097 public/index.php';
        
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", __DIR__ . '/server_webhooksubscriptiontest.log', "a"],
            2 => ["file", __DIR__ . '/server_webhooksubscriptiontest.log', "a"],
        
        $env = array_merge($_ENV, [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $dbPath,
            'APP_ENV' => 'testing',
        
                putenv("DB_DATABASE={$dbPath}");
        $_ENV['DB_DATABASE'] = $dbPath;
        $_SERVER['DB_DATABASE'] = $dbPath;
        
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => $dbPath,
            'prefix'   => '',
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        
        require_once __DIR__ . '/../../../src/Infrastructure/Persistence/sqlite_setup.php';
        \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema($capsule->getConnection());

        self::$serverProcess = proc_open($cmd, $descriptors, $pipes, $baseDir, $env);
        
        // Wait for server to bind
            }
            usleep(50000); // 50ms
        }
    }

        public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess && is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    {



    }

    {





    }

    {

            'next_attempt_at' => (new \DateTime('-10 minutes'))->format('Y-m-d H:i:s'),

        $phpBin = PHP_BINARY;
        $dbPath = __DIR__ . '/../../../storage/data/test_webhooksubscriptiontest.sqlite';

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "set DB_CONNECTION=sqlite&& set DB_DATABASE={$dbPath}&& \"{$phpBin}\" -d extension_dir=\"{$extDir}\" -d extension=mbstring -d extension=pdo_sqlite scripts/webhook-worker.php --once";
        } else {
            $cmd = "DB_CONNECTION=sqlite DB_DATABASE=" . escapeshellarg($dbPath) . " php scripts/webhook-worker.php --once";
        }

        exec($cmd, $output, $resultCode);

    }

    {
        $url = 'http://127.0.0.1:8097' . $path;

        }

        
        }

    }
}





{

    {
        
            }
        }
    }

    {
        }
    }

    {



    }

    {





    }

    {


        $resultCode = -1;
        exec("php scripts/webhook-worker.php --once", $output, $resultCode);

    }

    {

        }

        
        }

    }
}
