<?php

namespace Tests\Unit\Application\Returns\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Returns\UseCases\ResolveQuarantineItem;
use InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Returns\Aggregates\QuarantineItem;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Returns\Enums\QuarantineStatus;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use Exception;

class ResolveQuarantineItemTest extends TestCase
{
    private $quarantineRepoMock;
    private $productRepoMock;
    private $costLayerRepoMock;
    private $journalServiceMock;
    private $useCase;

    protected function setUp(): void
    {
        $this->quarantineRepoMock = $this->createMock(QuarantineRepositoryInterface::class);
        $this->productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $this->costLayerRepoMock = $this->createMock(CostLayerRepositoryInterface::class);
        $this->journalServiceMock = $this->createMock(AccountingJournalService::class);

        $this->useCase = new ResolveQuarantineItem(
            $this->quarantineRepoMock,
            $this->productRepoMock,
            $this->costLayerRepoMock,
            $this->journalServiceMock
        );
    }

    private function createQuarantineItem(string $id, string $variantId, int $quantity, string $locationIdStr): QuarantineItem
    {
        return new QuarantineItem(
            $id,
            $variantId,
            $quantity,
            'Defective',
            new LocationId($locationIdStr),
            new TenantId('tenant-1')
        );
    }

    private function createProduct(string $variantId): Product
    {
        return Product::create(
            $variantId,
            new SKU('TEST-SKU'),
            'Test Product',
            new Department('GENERAL'),
            new LocationId('LOC-1'),
            new Quantity(10)
        );
    }

    public function testExecuteThrowsExceptionWhenItemNotFound()
    {
        $this->quarantineRepoMock->expects($this->once())
            ->method('findById')
            ->with('q-123')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Quarantine item with ID q-123 not found.");

        $this->useCase->execute(['quarantineItemId' => 'q-123', 'resolution' => 'RESTOCK']);
    }

    public function testExecuteThrowsExceptionWhenProductNotFound()
    {
        $qItem = $this->createQuarantineItem('q-123', 'v-1', 5, 'LOC-1');

        $this->quarantineRepoMock->expects($this->once())
            ->method('findById')
            ->with('q-123')
            ->willReturn($qItem);

        $this->productRepoMock->expects($this->once())
            ->method('findById')
            ->with('v-1')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product not found for variant v-1");

        $this->useCase->execute(['quarantineItemId' => 'q-123', 'resolution' => 'RESTOCK']);
    }

    public function testExecuteResolvesRestockAndUpdatesStock()
    {
        $qItem = $this->createQuarantineItem('q-123', 'v-1', 5, 'LOC-1');
        $product = $this->createProduct('v-1');

        // Ensure product has stock in quarantine location
        $product->receiveStockAt(new LocationId('LOC-1-quarantine'), new Quantity(5), 'TEST-IN');

        $this->quarantineRepoMock->expects($this->once())
            ->method('findById')
            ->with('q-123')
            ->willReturn($qItem);

        $this->productRepoMock->expects($this->once())
            ->method('findById')
            ->with('v-1')
            ->willReturn($product);

        $this->productRepoMock->expects($this->exactly(2))
            ->method('save');

        $this->quarantineRepoMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (QuarantineItem $item) {
                return $item->getStatus() === QuarantineStatus::Restocked;
            }));

        $this->useCase->execute(['quarantineItemId' => 'q-123', 'resolution' => 'RESTOCK']);

        // Assert stock levels were adjusted correctly
        $this->assertEquals(0, $product->getStockAt(new LocationId('LOC-1-quarantine'))->getStockQuantity()->getValue());
        // Since createProduct adds 10 to LOC-1 and we received 5, it should be 15
        $this->assertEquals(15, $product->getStockAt(new LocationId('LOC-1'))->getStockQuantity()->getValue());
    }

    public function testExecuteResolvesScrapConsumesCostLayerAndPostsJournal()
    {
        $qItem = $this->createQuarantineItem('q-123', 'v-1', 2, 'LOC-1');
        $product = $this->createProduct('v-1');
        $product->receiveStockAt(new LocationId('LOC-1-quarantine'), new Quantity(2), 'TEST-IN');

        $this->quarantineRepoMock->expects($this->once())
            ->method('findById')
            ->with('q-123')
            ->willReturn($qItem);

        $this->productRepoMock->expects($this->once())
            ->method('findById')
            ->with('v-1')
            ->willReturn($product);

        $costLayer = new InventoryCostLayer(
            'layer-1',
            'v-1',
            'tenant-1',
            10,
            1000,
            new \DateTimeImmutable()
        );

        $this->costLayerRepoMock->expects($this->once())
            ->method('getActiveLayers')
            ->with('v-1', 'received_at ASC')
            ->willReturn([$costLayer]);

        $this->journalServiceMock->expects($this->once())
            ->method('onInventoryWriteOff')
            ->with('tenant-1', 'q-123', 2000, $this->isInstanceOf(\DateTimeImmutable::class));

        $this->quarantineRepoMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (QuarantineItem $item) {
                return $item->getStatus() === QuarantineStatus::Scrapped;
            }));

        $this->useCase->execute(['quarantineItemId' => 'q-123', 'resolution' => 'SCRAP']);
    }

    public function testExecuteResolvesRtvConsumesCostLayerAndPostsJournal()
    {
        $qItem = $this->createQuarantineItem('q-123', 'v-1', 3, 'LOC-1');
        $product = $this->createProduct('v-1');
        $product->receiveStockAt(new LocationId('LOC-1-quarantine'), new Quantity(3), 'TEST-IN');

        $this->quarantineRepoMock->expects($this->once())
            ->method('findById')
            ->with('q-123')
            ->willReturn($qItem);

        $this->productRepoMock->expects($this->once())
            ->method('findById')
            ->with('v-1')
            ->willReturn($product);

        $costLayer = new InventoryCostLayer(
            'layer-1',
            'v-1',
            'tenant-1',
            10,
            1500,
            new \DateTimeImmutable()
        );

        $this->costLayerRepoMock->expects($this->once())
            ->method('getActiveLayers')
            ->with('v-1', 'received_at ASC')
            ->willReturn([$costLayer]);

        $this->journalServiceMock->expects($this->once())
            ->method('onReturnToVendor')
            ->with('tenant-1', 'q-123', 4500, $this->isInstanceOf(\DateTimeImmutable::class));

        $this->quarantineRepoMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (QuarantineItem $item) {
                return $item->getStatus() === QuarantineStatus::Rtv;
            }));

        $this->useCase->execute(['quarantineItemId' => 'q-123', 'resolution' => 'RTV']);
    }
}
