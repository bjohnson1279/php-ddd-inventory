<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\RfidController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Models\RfidTagModel;
use InventoryApp\Infrastructure\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;

class RfidRequestMock implements RequestInterface
{
    private string $body;

    public function __construct(string $body = '') {
        $this->body = $body;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function validate(array $rules): array { return []; }
    public function query(string $key, $default = null) { return $default; }
}

class RfidControllerTest extends TestCase
{
    private RfidController $controller;

    public static function setUpBeforeClass(): void
    {
        // For standalone execution
        try {
            Capsule::connection();
        } catch (\Throwable $e) {
            $capsule = new Capsule;
            $capsule->addConnection([
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ], 'default');

            // To prevent Call to a member function connection() on null:
            // Since we caught the exception, we must set it globally.
            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            // And now try to get connection, but store it explicitly
            \Illuminate\Database\Capsule\Manager::connection();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['auth.tenant_id'] = 'tenant-1';
        $this->controller = new RfidController();

        // Avoid schema manipulation if we are running in full integration suite where tables exist
        try {
            Capsule::table('rfid_tags')->delete();
        } catch (\Exception $e) {
            // Probably sqlite memory without schema
            if (!Capsule::schema()->hasTable('rfid_tags')) {
                Capsule::schema()->create('rfid_tags', function ($table) {
                    $table->string('epc')->primary();
                    $table->string('sku');
                    $table->string('serial_number');
                    $table->string('status');
                    $table->string('last_seen_at')->nullable();
                    $table->string('last_location')->nullable();
                    $table->string('created_at');
                });
            }
        }
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.tenant_id']);
        parent::tearDown();
    }

    public function testAssignReturns400OnMissingFields(): void
    {
        $requestMock = new RfidRequestMock(json_encode(['epc' => 'abc']));

        $response = $this->controller->assign($requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing required fields: epc, sku, serialNumber', $response->getContent());
    }

    public function testAssignReturns400OnInvalidEpc(): void
    {
        $requestMock = new RfidRequestMock(json_encode([
            'epc' => 'invalid-epc',
            'sku' => 'SKU-1',
            'serialNumber' => 'SN-1'
        ]));

        $response = $this->controller->assign($requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('RFID EPC must be a 24-character hexadecimal string.', $response->getContent());
    }

    public function testAssignReturns500OnException(): void
    {
        // To simulate a DB exception cleanly without messing up other test's schemas:
        // We will insert a tag, then insert the SAME tag again to trigger a primary key duplicate exception.
        $epc = '0123456789abcdef01234567';

        RfidTagModel::create([
            'epc' => $epc,
            'sku' => 'SKU-EXISTING',
            'serial_number' => 'SN-EXISTING',
            'status' => 'ACTIVE',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $requestMock = new RfidRequestMock(json_encode([
            'epc' => $epc,
            'sku' => 'SKU-1',
            'serialNumber' => 'SN-1'
        ]));

        $response = $this->controller->assign($requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        // Since we are forcing a duplicate, it will have 'Unique violation' or similar in message.
        // We can just verify it returns a 500.
    }

    public function testAssignSuccessReturns201AndCreatesRecord(): void
    {
        $epc = '0123456789abcdef01234567';
        $requestMock = new RfidRequestMock(json_encode([
            'epc' => $epc,
            'sku' => 'SKU-1',
            'serialNumber' => 'SN-1'
        ]));

        $response = $this->controller->assign($requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('Tag assigned successfully', $response->getContent());

        $tag = RfidTagModel::where('epc', $epc)->first();
        $this->assertNotNull($tag);
        $this->assertEquals('SKU-1', $tag->sku);
        $this->assertEquals('SN-1', $tag->serial_number);
        $this->assertEquals('ACTIVE', $tag->status);
    }
}
