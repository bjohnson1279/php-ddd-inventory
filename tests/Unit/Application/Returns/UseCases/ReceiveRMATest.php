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
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Returns\Aggregates\QuarantineItem;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use Exception;

class ReceiveRMATest extends TestCase
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

    public function testExecuteSuccessfullyReceivesRMAForRestock(): void
    {
        $dto = [
            'rmaId' => 'rma_1',
            'items' => [
                [
                    'variantId' => 'var_1',
                    'quantityReceived' => 5,
                    'disposition' => 'RESTOCK',
                    'serialNumbers' => []
                ]
            ]
        ];

        $rmaItem = $this->createRmaItemMock('var_1', 1000);
        $rma = $this->createRmaMock('rma_1', 'tenant_1', 'LOC-1', [$rmaItem]);
        $product = $this->createProductMock();

        $this->rmaRepositoryMock->expects($this->once())
            ->method('findById')
            ->with('rma_1')
            ->willReturn($rma);

        $rma->expects($this->once())
            ->method('receiveItem')
            ->with('var_1', 5, RMADisposition::Restock);

        $this->productRepositoryMock->expects($this->once())
            ->method('findById')
            ->with('var_1')
            ->willReturn($product);

        $product->expects($this->once())
            ->method('receiveStockAt')
            ->with(
                $this->callback(fn(LocationId $loc) => $loc->getValue() === 'LOC-1'),
                $this->callback(fn($qty) => $qty->getValue() === 5),
                'RMA-rma_1'
            );

        $this->productRepositoryMock->expects($this->once())
            ->method('save')
            ->with($product);

        $this->costLayerRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(InventoryCostLayer::class));

        $this->journalServiceMock->expects($this->once())
            ->method('onStockReturned')
            ->with('tenant_1', 'var_1', 5000, 'rma_1', $this->isInstanceOf(\DateTimeImmutable::class));

        $this->rmaRepositoryMock->expects($this->once())
            ->method('save')
            ->with($rma);

        $this->useCase->execute($dto);
    }

    public function testExecuteThrowsExceptionIfRmaNotFound(): void
    {
        $this->rmaRepositoryMock->expects($this->once())
            ->method('findById')
            ->with('invalid_rma')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('RMA with ID invalid_rma not found.');

        $this->useCase->execute(['rmaId' => 'invalid_rma']);
    }

    public function testExecuteThrowsExceptionIfRmaItemNotFound(): void
    {
        $dto = [
            'rmaId' => 'rma_1',
            'items' => [
                ['variantId' => 'invalid_var']
            ]
        ];

        $rmaItem = $this->createRmaItemMock('var_1', 1000);
        $rma = $this->createRmaMock('rma_1', 'tenant_1', 'LOC-1', [$rmaItem]);

        $this->rmaRepositoryMock->method('findById')->willReturn($rma);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Item with variant ID invalid_var not found in RMA.');

        $this->useCase->execute($dto);
    }

    public function testExecuteThrowsExceptionIfProductNotFound(): void
    {
        $dto = [
            'rmaId' => 'rma_1',
            'items' => [
                [
                    'variantId' => 'var_1',
                    'quantityReceived' => 5,
                    'disposition' => 'RESTOCK'
                ]
            ]
        ];

        $rmaItem = $this->createRmaItemMock('var_1', 1000);
        $rma = $this->createRmaMock('rma_1', 'tenant_1', 'LOC-1', [$rmaItem]);

        $this->rmaRepositoryMock->method('findById')->willReturn($rma);
        $this->productRepositoryMock->method('findById')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Product not found for variant var_1');

        $this->useCase->execute($dto);
    }

    public function testExecuteHandlesQuarantineDisposition(): void
    {
        $dto = [
            'rmaId' => 'rma_1',
            'items' => [
                [
                    'variantId' => 'var_1',
                    'quantityReceived' => 5,
                    'disposition' => 'QUARANTINE',
                    'serialNumbers' => []
                ]
            ]
        ];

        $rmaItem = $this->createRmaItemMock('var_1', 1000);
        $rma = $this->createRmaMock('rma_1', 'tenant_1', 'LOC-1', [$rmaItem]);
        $product = $this->createProductMock();

        $this->rmaRepositoryMock->method('findById')->willReturn($rma);
        $this->productRepositoryMock->method('findById')->willReturn($product);

        $product->expects($this->once())
            ->method('receiveStockAt')
            ->with(
                $this->callback(fn(LocationId $loc) => $loc->getValue() === 'LOC-1-quarantine'),
                $this->callback(fn($qty) => $qty->getValue() === 5),
                'RMA-rma_1'
            );

        $this->quarantineRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(QuarantineItem::class));

        $this->useCase->execute($dto);
    }

    public function testExecuteHandlesScrapDisposition(): void
    {
        $dto = [
            'rmaId' => 'rma_1',
            'items' => [
                [
                    'variantId' => 'var_1',
                    'quantityReceived' => 5,
                    'disposition' => 'SCRAP',
                    'serialNumbers' => []
                ]
            ]
        ];

        $rmaItem = $this->createRmaItemMock('var_1', 1000);
        $rma = $this->createRmaMock('rma_1', 'tenant_1', 'LOC-1', [$rmaItem]);
        $product = $this->createProductMock();

        $this->rmaRepositoryMock->method('findById')->willReturn($rma);
        $this->productRepositoryMock->method('findById')->willReturn($product);

        $product->expects($this->once())
            ->method('dispatchStockAt')
            ->with(
                $this->callback(fn(LocationId $loc) => $loc->getValue() === 'LOC-1'),
                $this->callback(fn($qty) => $qty->getValue() === 5),
                'RMA-rma_1-SCRAP'
            );

        $this->journalServiceMock->expects($this->once())
            ->method('onInventoryWriteOff')
            ->with('tenant_1', 'rma_1', 5000, $this->isInstanceOf(\DateTimeImmutable::class));



        $mockLayer = new \InventoryApp\Domain\Accounting\Entities\InventoryCostLayer(
            'layer_1', 'var_1', 'tenant_1', 5, 1000, new \DateTimeImmutable(), 'ref'
        );

        $this->costLayerRepositoryMock->expects($this->once())
            ->method('getActiveLayers')
            ->willReturn([$mockLayer]);



        $this->useCase->execute($dto);
    }

    public function testExecuteHandlesSerializedItems(): void
    {
        $dto = [
            'rmaId' => 'rma_1',
            'items' => [
                [
                    'variantId' => 'var_1',
                    'quantityReceived' => 1,
                    'disposition' => 'RESTOCK',
                    'serialNumbers' => ['SN1']
                ]
            ]
        ];

        $rmaItem = $this->createRmaItemMock('var_1', 1000);
        $rma = $this->createRmaMock('rma_1', 'tenant_1', 'LOC-1', [$rmaItem]);
        $product = $this->createProductMock();
        $serialItem = $this->createMock(SerializedItem::class);

        $this->rmaRepositoryMock->method('findById')->willReturn($rma);
        $this->productRepositoryMock->method('findById')->willReturn($product);

        $this->serializedRepositoryMock->expects($this->once())
            ->method('findBySerial')
            ->with($this->callback(fn(SerialNumber $sn) => $sn->value === 'SN1'), 'tenant_1')
            ->willReturn($serialItem);

        $serialItem->expects($this->once())
            ->method('acceptReturn')
            ->with('rma_1', 'system');

        $serialItem->expects($this->once())
            ->method('restock')
            ->with('system', 'rma_1');

        $this->serializedRepositoryMock->expects($this->once())
            ->method('save')
            ->with($serialItem);

        $this->useCase->execute($dto);
    }
}
