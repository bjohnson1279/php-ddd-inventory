<?php

declare(strict_types=1);

namespace tests\Integration\Infrastructure\IoT;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Infrastructure\IoT\MqttIngestService;
use InventoryApp\Infrastructure\IoT\RfidBulkScanWorker;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentRfidTagRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentSerializedItemRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentOutboxRepository;
use InventoryApp\Domain\Rfid\RfidTag;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use InventoryApp\Infrastructure\Persistence\TenantConnectionPool;
use InventoryApp\Infrastructure\Persistence\TenantRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
use PhpMqtt\Client\MqttClient;
use PhpAmqpLib\Channel\AMQPChannel;

require_once __DIR__ . '/../../bootstrap.php';

/** @group integration */
final class RfidBulkScanTest extends TestCase
{
    private EloquentRfidTagRepository $rfidTagRepo;
    private EloquentSerializedItemRepository $serializedItemRepo;
    private EloquentProductRepository $productRepo;
    private EloquentOutboxRepository $outboxRepo;
    private TenantConnectionPool $pool;
    private Capsule $capsule;
    private EventDispatcherInterface $dispatcherMock;

    protected function setUp(): void
    {
        $this->capsule = \InventoryApp\Infrastructure\ServiceContainer::getInstance()->make(Capsule::class);
        
        // Truncate tables for fresh state
        Capsule::table('rfid_tags')->delete();
        Capsule::table('serialized_items')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('products')->delete();
        Capsule::table('outbox_events')->delete();
        Capsule::table('tenants')->delete();
        Capsule::table('locations')->delete();

        Capsule::table('tenants')->insertOrIgnore([
            ['id' => 'test-tenant', 'name' => 'Test Tenant']
        ]);

        Capsule::table('locations')->insertOrIgnore([
            ['id' => 'LOC-OLD', 'name' => 'Old Location', 'type' => 'WAREHOUSE'],
            ['id' => 'LOC-NEW', 'name' => 'New Location', 'type' => 'WAREHOUSE']
        ]);

        $this->rfidTagRepo = new EloquentRfidTagRepository();
        $this->serializedItemRepo = new EloquentSerializedItemRepository();
        $this->productRepo = new EloquentProductRepository('test-tenant');
        $this->outboxRepo = new EloquentOutboxRepository();
        
        $registry = new TenantRegistry($this->capsule);
        $this->pool = new TenantConnectionPool($this->capsule, $registry);
        
        $this->dispatcherMock = $this->createMock(EventDispatcherInterface::class);
    }

    public function test_mqtt_ingest_service_publishes_to_amqp(): void
    {
        $mqttMock = $this->createMock(MqttClient::class);
        $amqpChannelMock = $this->createMock(AMQPChannel::class);

        $amqpChannelMock->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(function ($msg) {
                    $body = json_decode($msg->body, true);
                    return $body['tenantId'] === 'test-tenant' &&
                           $body['locationId'] === 'WH1-ZONEA' &&
                           count($body['tags']) === 2 &&
                           $body['tags'][0]['epc'] === 'E28011912000789A11111111' &&
                           $body['tags'][1]['epc'] === 'E28011912000789A22222222';
                }),
                $this->equalTo(''),
                $this->equalTo('rfid_bulk_scans')
            );

        $mqttIngest = $this->getMockBuilder(MqttIngestService::class)
            ->setConstructorArgs(['mqtt://localhost:1883', 'amqp://localhost:5672'])
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(MqttIngestService::class);
        
        $propClient = $ref->getProperty('mqttClient');
        $propClient->setAccessible(true);
        $propClient->setValue($mqttIngest, $mqttMock);

        $propChannel = $ref->getProperty('amqpChannel');
        $propChannel->setAccessible(true);
        $propChannel->setValue($mqttIngest, $amqpChannelMock);

        $method = $ref->getMethod('handleMqttMessage');
        $method->setAccessible(true);

        $mqttPayload = json_encode([
            'locationId' => 'WH1-ZONEA',
            'tags' => [
                ['epc' => 'E28011912000789A11111111', 'rssi' => -55],
                ['epc' => 'E28011912000789A22222222', 'rssi' => -60]
            ]
        ]);

        $method->invoke($mqttIngest, 'tenants/test-tenant/rfid/scans', $mqttPayload);
    }

    public function test_rfid_bulk_scan_worker_relocates_serials_and_updates_stocks(): void
    {
        // 1. Seed RFID Tags
        $tagA = new RfidTag('E28011912000789A11111111', 'SKU-A', new SerialNumber('SN-A101'));
        $tagB = new RfidTag('E28011912000789A22222222', 'SKU-A', new SerialNumber('SN-A102'));
        $this->rfidTagRepo->save('test-tenant', $tagA);
        $this->rfidTagRepo->save('test-tenant', $tagB);

        // 2. Seed Serialized Items
        $serialA = new SerializedItem(
            id: '11111111-1111-1111-1111-111111111111',
            variantId: 'variant-a',
            serialNumber: new SerialNumber('SN-A101'),
            tenantId: 'test-tenant',
            locationId: 'LOC-OLD',
            initialStatus: SerializedItemStatus::InStock
        );
        $serialB = new SerializedItem(
            id: '22222222-2222-2222-2222-222222222222',
            variantId: 'variant-a',
            serialNumber: new SerialNumber('SN-A102'),
            tenantId: 'test-tenant',
            locationId: 'LOC-OLD',
            initialStatus: SerializedItemStatus::InStock
        );
        $this->serializedItemRepo->save($serialA);
        $this->serializedItemRepo->save($serialB);

        // 3. Seed Products and LocationStocks
        $product = Product::create(
            id: '33333333-3333-3333-3333-333333333333',
            sku: new SKU('SKU-A'),
            name: 'SKU A Product',
            department: new Department('GEN'),
            initialLocation: new LocationId('LOC-OLD'),
            initialStock: new Quantity(10)
        );
        $this->productRepo->save($product);

        // Run the worker processor directly with the payload
        $worker = new RfidBulkScanWorker(
            amqpUrl: 'amqp://localhost:5672',
            rfidTagRepo: $this->rfidTagRepo,
            serializedItemRepo: $this->serializedItemRepo,
            productRepo: $this->productRepo,
            outboxRepo: $this->outboxRepo,
            connectionPool: $this->pool,
            capsule: $this->capsule,
            dispatcher: $this->dispatcherMock
        );

        $payload = [
            'tenantId' => 'test-tenant',
            'locationId' => 'LOC-NEW',
            'tags' => [
                ['epc' => 'E28011912000789A11111111'],
                ['epc' => 'E28011912000789A22222222'],
                ['epc' => 'E28011912000789A99999999'] // Unmatched
            ]
        ];

        $worker->processScanBatch($payload);

        // 4. Assertions
        // A. RFID Tags updated lastSeenAt and lastLocation
        $updatedTagA = $this->rfidTagRepo->findByEpc('test-tenant', 'E28011912000789A11111111');
        $updatedTagB = $this->rfidTagRepo->findByEpc('test-tenant', 'E28011912000789A22222222');
        
        $this->assertNotNull($updatedTagA);
        $this->assertEquals('LOC-NEW', $updatedTagA->lastLocation);
        $this->assertNotNull($updatedTagA->lastSeenAt);

        $this->assertNotNull($updatedTagB);
        $this->assertEquals('LOC-NEW', $updatedTagB->lastLocation);
        $this->assertNotNull($updatedTagB->lastSeenAt);

        // B. Serial items relocated
        $updatedItemA = $this->serializedItemRepo->findBySerial(new SerialNumber('SN-A101'), 'test-tenant');
        $updatedItemB = $this->serializedItemRepo->findBySerial(new SerialNumber('SN-A102'), 'test-tenant');

        $this->assertNotNull($updatedItemA);
        $this->assertEquals('LOC-NEW', $updatedItemA->locationId());

        $this->assertNotNull($updatedItemB);
        $this->assertEquals('LOC-NEW', $updatedItemB->locationId());

        // C. Inventory quantities adjusted (OLD: 10 - 2 = 8, NEW: 0 + 2 = 2)
        $prodAfter = $this->productRepo->findBySku(new SKU('SKU-A'));
        $this->assertNotNull($prodAfter);
        
        $oldStock = $prodAfter->getStockAt(new LocationId('LOC-OLD'));
        $newStock = $prodAfter->getStockAt(new LocationId('LOC-NEW'));

        $this->assertEquals(8, $oldStock->getStockQuantity()->getValue());
        $this->assertEquals(2, $newStock->getStockQuantity()->getValue());

        // D. Outbox Event processed and registered
        $outboxEvents = $this->outboxRepo->fetchPending(10, 5);
        $this->assertCount(1, $outboxEvents);
        $this->assertEquals('RfidScanProcessedEvent', $outboxEvents[0]->eventName);

        $eventPayload = json_decode($outboxEvents[0]->payload, true);
        $this->assertEquals('test-tenant', $eventPayload['tenantId']);
        $this->assertEquals('LOC-NEW', $eventPayload['locationId']);
        $this->assertEquals(3, $eventPayload['totalCount']);
        $this->assertEquals(2, $eventPayload['matchedCount']);
        $this->assertEquals(1, $eventPayload['unmatchedCount']);
        $this->assertEquals(['E28011912000789A99999999'], $eventPayload['unmatchedEpcs']);
    }
}
