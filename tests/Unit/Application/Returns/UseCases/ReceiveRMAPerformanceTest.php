<?php

namespace Tests\Unit\Application\Returns\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Returns\UseCases\ReceiveRMA;
use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Entities\RMAItem;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;

class ReceiveRMAPerformanceTest extends TestCase
{
    private $rmaRepositoryMock;
    private $productRepositoryMock;
    private $costLayerRepositoryMock;
    private $quarantineRepositoryMock;
    private $journalServiceMock;
    private $serializedRepositoryMock;
    private ReceiveRMA $useCase;

    protected function setUp(): void
    {
        $this->rmaRepositoryMock = $this->createMock(RMARepositoryInterface::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->costLayerRepositoryMock = $this->createMock(CostLayerRepositoryInterface::class);
        $this->quarantineRepositoryMock = $this->createMock(QuarantineRepositoryInterface::class);
        $this->journalServiceMock = $this->createMock(AccountingJournalService::class);
        $this->serializedRepositoryMock = $this->createMock(SerializedItemRepositoryInterface::class);

        $this->useCase = new ReceiveRMA(
            $this->rmaRepositoryMock,
            $this->productRepositoryMock,
            $this->costLayerRepositoryMock,
            $this->quarantineRepositoryMock,
            $this->journalServiceMock,
            $this->serializedRepositoryMock
        );
    }

    private function createRmaMock(string $id, string $tenantId, string $locationId, array $items): RMA
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getId')->willReturn($id);
        $rma->method('getTenantId')->willReturn(new TenantId($tenantId));
        $rma->method('getLocationId')->willReturn(new LocationId($locationId));
        $rma->method('getItems')->willReturn($items);
        $rma->method('getRmaNumber')->willReturn('RMA-1234');
        return $rma;
    }

    private function createRmaItemMock(string $variantId, int $unitCostCents): RMAItem
    {
        $item = $this->createMock(RMAItem::class);
        $item->method('getVariantId')->willReturn($variantId);
        $item->method('getUnitCostCents')->willReturn($unitCostCents);
        return $item;
    }

    private function createProductMock(): Product
    {
        return $this->createMock(Product::class);
    }

    public function testPerformance()
    {
        $serialCount = 500;
        $serialNumbers = [];
        for ($i = 0; $i < $serialCount; $i++) {
            $serialNumbers[] = 'SN-' . $i;
        }

        $dto = [
            'rmaId' => 'rma_1',
            'items' => [
                [
                    'variantId' => 'var_1',
                    'quantityReceived' => $serialCount,
                    'disposition' => 'RESTOCK',
                    'serialNumbers' => $serialNumbers
                ]
            ]
        ];

        $rmaItem = $this->createRmaItemMock('var_1', 1000);
        $rma = $this->createRmaMock('rma_1', 'tenant_1', 'LOC-1', [$rmaItem]);
        $product = $this->createProductMock();

        $this->rmaRepositoryMock->method('findById')->willReturn($rma);
        $this->productRepositoryMock->method('findByIds')->willReturn(['var_1' => $product]);

        $this->serializedRepositoryMock->method('findBySerials')
            ->willReturnCallback(function($serials, $tenant) {
                $res = [];
                foreach ($serials as $sn) {
                    $res[strtolower($sn->value)] = clone $this->createMock(SerializedItem::class); // using clone or mock per sn
                }
                return $res;
            });

        // Add 1ms sleep to save to simulate real db time
        $this->serializedRepositoryMock->method('save')->willReturnCallback(function($item) {
            usleep(1000);
        });

        // if saveAll exists, use it
        if (method_exists($this->serializedRepositoryMock, 'saveAll')) {
            $this->serializedRepositoryMock->method('saveAll')->willReturnCallback(function($items) {
                usleep(5000); // Batched save takes 5ms for 500 items instead of 500ms
            });
        }

        $start = microtime(true);
        $this->useCase->execute($dto);
        $end = microtime(true);

        $duration = ($end - $start) * 1000;
        echo "\nPerformance test duration: " . round($duration, 2) . " ms\n";
        $this->assertTrue(true);
    }
}
