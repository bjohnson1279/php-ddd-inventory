<?php

require 'vendor/autoload.php';

use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\CostLayerService;
use InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown;

class DummyRepo implements CostLayerRepositoryInterface {
    public int $saveCalls = 0;
    public int $saveBatchCalls = 0;
    public array $activeLayers = [];

    public function getActiveLayers(string $variantId, string $orderBy = 'received_at ASC'): array {
        return $this->activeLayers;
    }
    public function save(InventoryCostLayer $layer): void {
        $this->saveCalls++;
    }
    public function saveBatch(array $layers): void {
        $this->saveBatchCalls++;
    }
    public function findBySerial(string $variantId, string $serialNumber): ?InventoryCostLayer {
        return null;
    }
}

$repo = new DummyRepo();
$service = new CostLayerService($repo);

// Generate 1000 layers
$layers = [];
for ($i = 0; $i < 1000; $i++) {
    $layers[] = new InventoryCostLayer("l$i", "v1", "t1", 10, 1000, new \DateTimeImmutable());
}
$repo->activeLayers = $layers;

$start = microtime(true);
$service->consumeFifoLayers("v1", 1000 * 10);
$end = microtime(true);

echo "Time taken: " . ($end - $start) . " seconds\n";
echo "save calls: " . $repo->saveCalls . "\n";
echo "saveBatch calls: " . $repo->saveBatchCalls . "\n";
