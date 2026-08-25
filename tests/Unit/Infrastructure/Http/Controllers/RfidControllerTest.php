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
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new RfidController();
        Capsule::table('rfid_tags')->delete();
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
        // To simulate a DB exception, we can drop the table temporarily
        Capsule::schema()->drop('rfid_tags');

        $requestMock = new RfidRequestMock(json_encode([
            'epc' => '0123456789abcdef01234567',
            'sku' => 'SKU-1',
            'serialNumber' => 'SN-1'
        ]));

        $response = $this->controller->assign($requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('no such table: rfid_tags', $response->getContent());

        // Recreate it for subsequent tests
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
