<?php

namespace InventoryApp\Infrastructure\IoT;

use InventoryApp\Domain\Rfid\RfidTagRepositoryInterface;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Rfid\RfidScanProcessedEvent;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Infrastructure\Persistence\TenantConnectionPool;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\EventDispatcher\EventDispatcherInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RfidBulkScanWorker
{
    private ?AMQPStreamConnection $amqpConnection = null;
    private $amqpChannel = null;

    public function __construct(
        private readonly string $amqpUrl,
        private readonly RfidTagRepositoryInterface $rfidTagRepo,
        private readonly SerializedItemRepositoryInterface $serializedItemRepo,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly OutboxRepositoryInterface $outboxRepo,
        private readonly TenantConnectionPool $connectionPool,
        private readonly Capsule $capsule,
        private readonly EventDispatcherInterface $dispatcher
    ) {}

    public function start(): void
    {
        try {
            $amqpParts = parse_url($this->amqpUrl);
            $host = $amqpParts['host'] ?? 'localhost';
            $port = $amqpParts['port'] ?? 5672;
            $user = $amqpParts['user'] ?? 'guest';
            $pass = $amqpParts['pass'] ?? 'guest';
            $vhost = isset($amqpParts['path']) && $amqpParts['path'] !== '/' ? substr($amqpParts['path'], 1) : '/';

            $this->amqpConnection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
            $this->amqpChannel = $this->amqpConnection->channel();
            $this->amqpChannel->queue_declare('rfid_bulk_scans', false, true, false, false);
            $this->amqpChannel->basic_qos(null, 1, null);

            echo json_encode([
                "context" => "RfidBulkScanWorker",
                "message" => "[RfidBulkScanWorker] Started and listening to AMQP queue \"rfid_bulk_scans\""
            ]) . "\n";

            $callback = function ($msg) {
                try {
                    $payload = json_decode($msg->body, true);
                    $this->processScanBatch($payload);
                    $msg->ack();
                } catch (\Throwable $e) {
                    echo json_encode([
                        "context" => "RfidBulkScanWorker",
                        "message" => "Failed to process scan batch, dead-lettering message: " . $e->getMessage()
                    ]) . "\n";
                    $msg->nack(false, false);
                }
            };

            $this->amqpChannel->basic_consume('rfid_bulk_scans', '', false, false, false, false, $callback);

            while ($this->amqpChannel->is_consuming()) {
                $this->amqpChannel->wait();
            }

        } catch (\Throwable $e) {
            echo json_encode([
                "context" => "RfidBulkScanWorker",
                "message" => "Failed to start RfidBulkScanWorker: " . $e->getMessage()
            ]) . "\n";
            throw $e;
        }
    }

    public function processScanBatch(array $payload): void
    {
        $tenantId = $payload['tenantId'] ?? null;
        $locationId = $payload['locationId'] ?? null;
        $tags = $payload['tags'] ?? null;

        if (!$tenantId || !$locationId || !is_array($tags)) {
            throw new \InvalidArgumentException("Invalid batch payload received by worker.");
        }

        $isSqlite = $this->capsule->getConnection()->getDriverName() === 'sqlite';
        if (!$isSqlite) {
            $this->connectionPool->getConnection($tenantId);
            $this->capsule->getDatabaseManager()->setDefaultConnection('tenant_' . $tenantId);
        }

        try {
            echo json_encode([
                "context" => "RfidBulkScanWorker",
                "message" => "[RfidBulkScanWorker] Processing bulk scan of " . count($tags) . " tags at location \"{$locationId}\" for tenant \"{$tenantId}\""
            ]) . "\n";

            $epcs = array_map(fn($t) => $t['epc'], $tags);
            $registeredTags = $this->rfidTagRepo->findByEpcs($tenantId, $epcs);

            $matchedEpcs = [];
            foreach ($registeredTags as $rt) {
                $matchedEpcs[strtoupper($rt->epc)] = true;
            }

            $unmatchedEpcs = [];
            $matchedCount = 0;
            $unmatchedCount = 0;

            foreach ($tags as $tag) {
                if (isset($matchedEpcs[strtoupper($tag['epc'])])) {
                    $matchedCount++;
                } else {
                    $unmatchedCount++;
                    $unmatchedEpcs[] = $tag['epc'];
                }
            }

            foreach ($registeredTags as $regTag) {
                $regTag->lastSeenAt = new \DateTimeImmutable();
                $regTag->lastLocation = $locationId;
                $regTag->status = 'ACTIVE';

                $this->rfidTagRepo->save($tenantId, $regTag);

                $serialNo = new SerialNumber($regTag->serialNumber->value);
                $serialItem = $this->serializedItemRepo->findBySerial($serialNo, $tenantId);

                if ($serialItem) {
                    $oldLoc = $serialItem->locationId();
                    if ($oldLoc !== $locationId) {
                        $serialItem->scanCheckIn($locationId, 'rfid-scan-worker');
                        $this->serializedItemRepo->save($serialItem);

                        if ($oldLoc && $oldLoc !== 'default') {
                            $product = $this->productRepo->findBySku(new SKU($regTag->sku));
                            if ($product) {
                                $product->dispatchStockAt(new LocationId($oldLoc), new Quantity(1), "RFID relocation SN {$serialNo->value}");
                                $this->productRepo->save($product);
                            }
                        }

                        $product = $this->productRepo->findBySku(new SKU($regTag->sku));
                        if ($product) {
                            $product->receiveStockAt(new LocationId($locationId), new Quantity(1), "RFID relocation SN {$serialNo->value}");
                            $this->productRepo->save($product);
                        }

                        echo json_encode([
                            "context" => "RfidBulkScanWorker",
                            "message" => "[RfidBulkScanWorker] Successfully relocated serial number \"{$regTag->serialNumber->value}\" (SKU: {$regTag->sku}) from \"{$oldLoc}\" to \"{$locationId}\""
                        ]) . "\n";
                    }
                }
            }

            $scanEvent = new RfidScanProcessedEvent(
                "scan-batch-" . round(microtime(true) * 1000),
                $tenantId,
                $locationId,
                count($tags),
                $matchedCount,
                $unmatchedCount,
                $unmatchedEpcs
            );

            $this->outboxRepo->save($scanEvent);
            $this->dispatcher->dispatch($scanEvent);

            echo json_encode([
                "context" => "RfidBulkScanWorker",
                "message" => "[RfidBulkScanWorker] Completed scan processing. Matched: {$matchedCount}, Unmatched: {$unmatchedCount}"
            ]) . "\n";

        } finally {
            if (!$isSqlite) {
                $this->capsule->getDatabaseManager()->setDefaultConnection('default');
            }
        }
    }

    public function stop(): void
    {
        if ($this->amqpChannel) {
            $this->amqpChannel->close();
            $this->amqpChannel = null;
        }
        if ($this->amqpConnection) {
            $this->amqpConnection->close();
            $this->amqpConnection = null;
        }
        echo json_encode([
            "context" => "RfidBulkScanWorker",
            "message" => "Stopped RfidBulkScanWorker"
        ]) . "\n";
    }
}
