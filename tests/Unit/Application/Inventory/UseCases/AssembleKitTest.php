<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\AssembleKit;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use Exception;

class AssembleKitTest extends TestCase
{
    private $kitRepositoryMock;
    private $productRepositoryMock;
    private $ledgerRepositoryMock;
    private $costLayerRepositoryMock;
    private $journalServiceMock;
    private $useCase;

    protected function setUp(): void
    {
        $this->kitRepositoryMock = $this->createMock(KitRepositoryInterface::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepositoryMock = $this->createMock(LedgerRepositoryInterface::class);
        $this->costLayerRepositoryMock = $this->createMock(CostLayerRepositoryInterface::class);
        $this->journalServiceMock = $this->createMock(AccountingJournalService::class);

        $this->useCase = new AssembleKit(
            $this->kitRepositoryMock,
            $this->productRepositoryMock,
            $this->ledgerRepositoryMock,
            $this->costLayerRepositoryMock,
            $this->journalServiceMock
        );
    }

    public function testExecuteThrowsExceptionIfQuantityIsZeroOrLess()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Quantity to assemble must be greater than zero.");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 0,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteThrowsExceptionIfKitNotFound()
    {
        $this->kitRepositoryMock->method('findBySku')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Kit with SKU KIT-1 not found.");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteThrowsExceptionIfInsufficientStockForComponent()
    {
        $kit = new Kit('k-1', 'KIT-1', 'Test Kit');
        $kit->addComponent('comp-var-1', 2);

        $this->kitRepositoryMock->method('findBySku')->willReturn($kit);
        $this->ledgerRepositoryMock->method('currentQuantity')->with('comp-var-1')->willReturn(1); // Needs 2 * 1 = 2

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient stock for component variant ID comp-var-1. Needed: 2, Available: 1");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteThrowsExceptionIfComponentProductNotFound()
    {
        $kit = new Kit('k-1', 'KIT-1', 'Test Kit');
        $kit->addComponent('comp-var-1', 2);

        $this->kitRepositoryMock->method('findBySku')->willReturn($kit);
        $this->ledgerRepositoryMock->method('currentQuantity')->with('comp-var-1')->willReturn(5);

        // Mock CostLayerService consumption (inside cost layer repository)
        $costLayer = new InventoryCostLayer('cl-1', 'comp-var-1', 'tenant-1', 5, 1000, new \DateTimeImmutable());
        $this->costLayerRepositoryMock->method('getActiveLayers')->willReturn([$costLayer]);

        $this->productRepositoryMock->method('findById')->with('comp-var-1')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product variant comp-var-1 not found.");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteThrowsExceptionIfKitProductNotFound()
    {
        $kit = new Kit('k-1', 'KIT-1', 'Test Kit');
        $kit->addComponent('comp-var-1', 2);

        $this->kitRepositoryMock->method('findBySku')->willReturn($kit);
        $this->ledgerRepositoryMock->method('currentQuantity')->with('comp-var-1')->willReturn(5);

        $costLayer = new InventoryCostLayer('cl-1', 'comp-var-1', 'tenant-1', 5, 1000, new \DateTimeImmutable());
        $this->costLayerRepositoryMock->method('getActiveLayers')->willReturn([$costLayer]);

        $componentProduct = Product::create('comp-var-1', new SKU('COMP-1'), 'Comp 1', new Department('DEP'), new LocationId('LOC-1'), new Quantity(5));
        $this->productRepositoryMock->method('findById')->with('comp-var-1')->willReturn($componentProduct);

        $this->productRepositoryMock->method('findBySku')->with($this->callback(function($sku) { return $sku->getValue() === 'KIT-1'; }))->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product variant for Kit SKU KIT-1 not found.");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteSuccessfullyAssemblesKit()
    {
        // 1. Kit
        $kit = new Kit('k-1', 'KIT-1', 'Test Kit');
        $kit->addComponent('comp-var-1', 2);
        $this->kitRepositoryMock->expects($this->once())->method('findBySku')->with('KIT-1')->willReturn($kit);

        // 2. Component stock check
        $this->ledgerRepositoryMock->expects($this->once())->method('currentQuantity')->with('comp-var-1')->willReturn(5);

        // 3. Consume cost layer (2 units * $10.00 = $20.00 total)
        $costLayer = new InventoryCostLayer('cl-1', 'comp-var-1', 'tenant-1', 5, 1000, new \DateTimeImmutable());
        $this->costLayerRepositoryMock->expects($this->once())->method('getActiveLayers')->with('comp-var-1')->willReturn([$costLayer]);

        // Component product deduction
        $componentProduct = Product::create('comp-var-1', new SKU('COMP-1'), 'Comp 1', new Department('DEP'), new LocationId('LOC-1'), new Quantity(5));
        $this->productRepositoryMock->expects($this->once())->method('findById')->with('comp-var-1')->willReturn($componentProduct);

        $this->productRepositoryMock->expects($this->any())->method('save'); // Once for component, once for kit

        // Ledger entries
        $this->ledgerRepositoryMock->expects($this->exactly(2))->method('append')->with($this->callback(function(LedgerEntry $entry) {
            return in_array($entry->variantId, ['comp-var-1', 'kit-var-1']) &&
                   $entry->reason === ReasonCode::KitAssembly &&
                   $entry->metadata['locationId'] === 'LOC-1';
        }));

        // Kit product increment
        $kitProduct = Product::create('kit-var-1', new SKU('KIT-1'), 'Test Kit', new Department('DEP'), new LocationId('LOC-1'), new Quantity(0));
        $this->productRepositoryMock->expects($this->once())->method('findBySku')->with($this->callback(function($sku) { return $sku->getValue() === 'KIT-1'; }))->willReturn($kitProduct);

        // Save new kit cost layer (unit cost = 2000 / 1 = 2000)
        $this->costLayerRepositoryMock->expects($this->any())->method('save')->with($this->callback(function(InventoryCostLayer $layer) {
            return $layer->variantId === 'kit-var-1' && $layer->unitCostCents === 2000;
        })); // The other save is saveBatch from consumeFifoLayers

        // Journal entry
        $this->journalServiceMock->expects($this->once())->method('onKitAssembly')->with('tenant-1', $this->anything(), 'KIT-1', 2000, 'ref-1');

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);

        $this->assertEquals(3, $componentProduct->getTotalStockQuantity()->getValue());
        $this->assertEquals(1, $kitProduct->getTotalStockQuantity()->getValue());
    }
}
