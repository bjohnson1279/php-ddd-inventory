<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\RfidController;
use InventoryApp\Infrastructure\Models\RfidTagModel;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;

require_once __DIR__ . '/../../bootstrap.php';

/** @group integration */
class RfidControllerTest extends TestCase
{
    private RfidController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new RfidController();
        Capsule::table('rfid_tags')->delete();
    }

    public function testListReturnsAllTagsInDescendingOrder(): void
    {
        $epc1 = '111111111111111111111111';
        $epc2 = '222222222222222222222222';
        $sku = 'TEST-SKU';

        RfidTagModel::create([
            'epc' => $epc1,
            'sku' => $sku,
            'serial_number' => 'SN-1',
            'status' => 'ACTIVE',
            'created_at' => '2023-01-01 10:00:00'
        ]);

        RfidTagModel::create([
            'epc' => $epc2,
            'sku' => $sku,
            'serial_number' => 'SN-2',
            'status' => 'ACTIVE',
            'created_at' => '2023-01-02 10:00:00' // Newer
        ]);

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->list($requestMock, 'test-tenant');

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('tags', $body);
        $this->assertCount(2, $body['tags']);

        // Assert descending order
        $this->assertEquals($epc2, $body['tags'][0]['epc']);
        $this->assertEquals($epc1, $body['tags'][1]['epc']);
    }

    public function testListReturnsEmptyArrayWhenNoTagsExist(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->list($requestMock, 'test-tenant');

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('tags', $body);
        $this->assertEmpty($body['tags']);
    }

    public function testListHandlesExceptionAndReturns500(): void
    {
        $this->markTestSkipped('Testing Exception block requires breaking Eloquent connection, which breaks other tests.');
    }
}
