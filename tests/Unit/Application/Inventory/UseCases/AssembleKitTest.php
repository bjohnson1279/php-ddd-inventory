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

        $this->productRepositoryMock->expects($this->never())->method('save');
        $this->ledgerRepositoryMock->expects($this->never())->method('append');
        $this->costLayerRepositoryMock->expects($this->never())->method('save');
        $this->journalServiceMock->expects($this->never())->method('onKitAssembly');

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

        $this->productRepositoryMock->expects($this->never())->method('save');
        $this->ledgerRepositoryMock->expects($this->never())->method('append');
        $this->costLayerRepositoryMock->expects($this->never())->method('save');
        $this->journalServiceMock->expects($this->never())->method('onKitAssembly');

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

        $kitProduct = Product::create('kit-var-1', new SKU('KIT-1'), 'Test Kit', new Department('DEP'), new LocationId('LOC-1'), new Quantity(0));
        $this->productRepositoryMock->method('findBySku')->with($this->callback(function($sku) { return $sku->getValue() === 'KIT-1'; }))->willReturn($kitProduct);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient stock for component variant ID comp-var-1. Needed: 2, Available: 1");

        $this->productRepositoryMock->expects($this->never())->method('save');
        $this->ledgerRepositoryMock->expects($this->never())->method('append');
        $this->costLayerRepositoryMock->expects($this->never())->method('save');
        $this->journalServiceMock->expects($this->never())->method('onKitAssembly');

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

        $kitProduct = Product::create('kit-var-1', new SKU('KIT-1'), 'Test Kit', new Department('DEP'), new LocationId('LOC-1'), new Quantity(0));
        $this->productRepositoryMock->method('findBySku')->with($this->callback(function($sku) { return $sku->getValue() === 'KIT-1'; }))->willReturn($kitProduct);

        // Mock CostLayerService consumption (inside cost layer repository)
        $costLayer = new InventoryCostLayer('cl-1', 'comp-var-1', 'tenant-1', 5, 1000, new \DateTimeImmutable());
        $this->costLayerRepositoryMock->method('getActiveLayers')->willReturn([$costLayer]);

        $this->productRepositoryMock->method('findById')->with('comp-var-1')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product variant comp-var-1 not found.");

        $this->productRepositoryMock->expects($this->never())->method('save');
        $this->ledgerRepositoryMock->expects($this->never())->method('append');
        $this->costLayerRepositoryMock->expects($this->never())->method('save');
        $this->journalServiceMock->expects($this->never())->method('onKitAssembly');

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

        $this->productRepositoryMock->expects($this->never())->method('save');
        $this->ledgerRepositoryMock->expects($this->never())->method('append');
        $this->costLayerRepositoryMock->expects($this->never())->method('save');
        $this->journalServiceMock->expects($this->never())->method('onKitAssembly');

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

    public function testExecuteSuccessfullyAssemblesKitWithMultipleComponentsAndQuantity()
    {
        // Setup kit with 2 components
        $kit = new Kit('k-1', 'KIT-1', 'Test Kit');
        $kit->addComponent('comp-var-1', 2);
        $kit->addComponent('comp-var-2', 3);
        $this->kitRepositoryMock->expects($this->once())->method('findBySku')->with('KIT-1')->willReturn($kit);

        // Pre-fetch kit product
        $kitProduct = Product::create('kit-var-1', new SKU('KIT-1'), 'Test Kit', new Department('DEP'), new LocationId('LOC-1'), new Quantity(0));
        $this->productRepositoryMock->expects($this->once())
             ->method('findBySku')
             ->with($this->callback(function($sku) { return $sku->getValue() === 'KIT-1'; }))
             ->willReturn($kitProduct);

        // Ledger checks for both components (assembling quantity 2)
        // comp-1 needs 2 * 2 = 4
        // comp-2 needs 3 * 2 = 6
        $this->ledgerRepositoryMock->expects($this->exactly(2))->method('currentQuantity')
             ->willReturnCallback(function($variantId) {
                 if ($variantId === 'comp-var-1') return 10;
                 if ($variantId === 'comp-var-2') return 15;
                 return 0;
             });

        // Product lookups
        $comp1 = Product::create('comp-var-1', new SKU('COMP-1'), 'Comp 1', new Department('DEP'), new LocationId('LOC-1'), new Quantity(10));
        $comp2 = Product::create('comp-var-2', new SKU('COMP-2'), 'Comp 2', new Department('DEP'), new LocationId('LOC-1'), new Quantity(15));
        $this->productRepositoryMock->expects($this->exactly(2))->method('findById')
             ->willReturnCallback(function($variantId) use ($comp1, $comp2) {
                 if ($variantId === 'comp-var-1') return $comp1;
                 if ($variantId === 'comp-var-2') return $comp2;
                 return null;
             });

        // Cost layer consumption
        // comp-1: 4 units @ $5 = $20
        $layer1 = new InventoryCostLayer('cl-1', 'comp-var-1', 'tenant-1', 10, 500, new \DateTimeImmutable());
        // comp-2: 6 units @ $10 = $60
        $layer2 = new InventoryCostLayer('cl-2', 'comp-var-2', 'tenant-1', 15, 1000, new \DateTimeImmutable());

        $this->costLayerRepositoryMock->expects($this->exactly(2))->method('getActiveLayers')
             ->willReturnCallback(function($variantId) use ($layer1, $layer2) {
                 if ($variantId === 'comp-var-1') return [$layer1];
                 if ($variantId === 'comp-var-2') return [$layer2];
                 return [];
             });

        // Ensure proper saves (2 for components, 1 for kit)
        $this->productRepositoryMock->expects($this->exactly(3))->method('save');

        // Ledger entries (2 for components deduction, 1 for kit increment)
        $this->ledgerRepositoryMock->expects($this->exactly(3))->method('append');

        // Total cost = $20 + $60 = $80. Quantity = 2. Unit cost = $40.
        $this->costLayerRepositoryMock->expects($this->any())->method('save')
             ->with($this->callback(function(InventoryCostLayer $layer) {
                 return $layer->variantId === 'kit-var-1' && $layer->unitCostCents === 4000;
             }));

        $this->journalServiceMock->expects($this->once())->method('onKitAssembly')
             ->with('tenant-1', $this->anything(), 'KIT-1', 8000, 'ref-1');

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 2,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);

        $this->assertEquals(6, $comp1->getTotalStockQuantity()->getValue()); // 10 - 4
        $this->assertEquals(9, $comp2->getTotalStockQuantity()->getValue()); // 15 - 6
        $this->assertEquals(2, $kitProduct->getTotalStockQuantity()->getValue()); // 0 + 2
    }
}
