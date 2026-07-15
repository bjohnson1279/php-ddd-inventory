<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Inventory\UseCases;

use InventoryApp\Application\Inventory\UseCases\DisassembleKit;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use PHPUnit\Framework\TestCase;

class DisassembleKitTest extends TestCase
{
    private KitRepositoryInterface $kitRepository;
    private ProductRepositoryInterface $productRepository;
    private LedgerRepositoryInterface $ledgerRepository;
    private CostLayerRepositoryInterface $costLayerRepository;
    private AccountingJournalService $journalService;
    private DisassembleKit $useCase;

    protected function setUp(): void
    {
        $this->kitRepository = $this->createMock(KitRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepository = $this->createMock(LedgerRepositoryInterface::class);
        $this->costLayerRepository = $this->createMock(CostLayerRepositoryInterface::class);
        $this->journalService = $this->createMock(AccountingJournalService::class);

        $this->useCase = new DisassembleKit(
            $this->kitRepository,
            $this->productRepository,
            $this->ledgerRepository,
            $this->costLayerRepository,
            $this->journalService
        );
    }

    public function testExecuteThrowsExceptionIfQuantityZeroOrLess(): void
    {
        $this->productRepository->expects($this->never())->method('save');
        $this->ledgerRepository->expects($this->never())->method('append');
        $this->costLayerRepository->expects($this->never())->method('saveBatch');
        $this->costLayerRepository->expects($this->never())->method('save');
        $this->journalService->expects($this->never())->method('onKitDisassembly');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Quantity to disassemble must be greater than zero.");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 0,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteThrowsExceptionWhenKitNotFound(): void
    {
        $this->productRepository->expects($this->never())->method('save');
        $this->ledgerRepository->expects($this->never())->method('append');
        $this->costLayerRepository->expects($this->never())->method('saveBatch');
        $this->costLayerRepository->expects($this->never())->method('save');
        $this->journalService->expects($this->never())->method('onKitDisassembly');

        $this->kitRepository->method('findBySku')->willReturn(null);

        $this->expectException(\Exception::class);
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

    public function testExecuteThrowsExceptionWhenKitProductNotFound(): void
    {
        $this->productRepository->expects($this->never())->method('save');
        $this->ledgerRepository->expects($this->never())->method('append');
        $this->costLayerRepository->expects($this->never())->method('saveBatch');
        $this->costLayerRepository->expects($this->never())->method('save');
        $this->journalService->expects($this->never())->method('onKitDisassembly');

        $kit = new Kit('kit-id', 'KIT-1', 'Test Kit');
        $this->kitRepository->method('findBySku')->willReturn($kit);

        $this->productRepository->method('findBySku')->willReturn(null);

        $this->expectException(\Exception::class);
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

    public function testExecuteThrowsExceptionWhenInsufficientStock(): void
    {
        $this->productRepository->expects($this->never())->method('save');
        $this->ledgerRepository->expects($this->never())->method('append');
        $this->costLayerRepository->expects($this->never())->method('saveBatch');
        $this->costLayerRepository->expects($this->never())->method('save');
        $this->journalService->expects($this->never())->method('onKitDisassembly');

        $expectedSku = 'KIT-1';
        $kit = new Kit('kit-id', $expectedSku, 'Test Kit');

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU($expectedSku),
            'Test Kit',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(5)
        );

        $this->kitRepository->method('findBySku')->willReturn($kit);

        $this->productRepository->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($expectedSku) {
                return $sku->getValue() === $expectedSku;
            }))
            ->willReturn($kitProduct);

        $this->ledgerRepository->method('currentQuantity')->willReturn(2); // insufficient

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Insufficient stock for Kit variant KIT-1. Needed: 5, Available: 2");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 5, // request 5, have 2
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteSuccessfullyDisassemblesKit(): void
    {
        $expectedSku = 'KIT-1';
        $kit = new Kit('kit-id', $expectedSku, 'Test Kit');
        $kit->addComponent('comp-1', 2);

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU($expectedSku),
            'Test Kit',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(5)
        );

        $compProduct = Product::create(
            'comp-1',
            new SKU('COMP-1'),
            'Test Component 1',
            new Department('PARTS'),
            new LocationId('LOC-1'),
            new Quantity(10)
        );

        $this->kitRepository->method('findBySku')->willReturn($kit);

        $this->productRepository->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($expectedSku) {
                return $sku->getValue() === $expectedSku;
            }))
            ->willReturn($kitProduct);

        $this->productRepository->method('findByIds')
            ->with(['comp-1'])
            ->willReturn(['comp-1' => $compProduct]);

        $this->ledgerRepository->method('currentQuantity')->willReturn(5);

        $kitLayer = new InventoryCostLayer('layer-1', 'prod_kit_1', 'tenant-1', 5, 2000, new \DateTimeImmutable(), 'ref-1');

        $compLayer = new InventoryCostLayer('layer-2', 'comp-1', 'tenant-1', 10, 1000, new \DateTimeImmutable(), 'ref-1');

        $this->costLayerRepository->method('getActiveLayers')->willReturnMap([
            ['prod_kit_1', 'received_at ASC', [$kitLayer]],
            ['comp-1', 'received_at ASC', [$compLayer]]
        ]);

        // We expect saveBatch to be called twice:
        // 1. By CostLayerService::consumeLayers (via DisassembleKit step 4) to update the remaining quantity of the kit layer.
        // 2. By DisassembleKit itself (step 8) to save the new component layers.
        $this->costLayerRepository->expects($this->exactly(2))->method('saveBatch')->willReturnCallback(function (array $layers) {
            if (count($layers) > 0 && $layers[0]->variantId === 'prod_kit_1') {
                return; // This is the call from CostLayerService
            }
            $this->assertCount(1, $layers);
            $this->assertEquals(1000, $layers[0]->unitCostCents);
        });

        $this->productRepository->expects($this->once())->method('save')->with($this->callback(function(Product $product) {
            return $product->getId() === 'prod_kit_1';
        }));
        $this->productRepository->expects($this->once())->method('saveAll')->with($this->callback(function(array $products) {
            return count($products) === 1 && $products[0]->getId() === 'comp-1';
        }));

        $this->ledgerRepository->expects($this->once())->method('append')->with($this->callback(function ($entry) {
            return $entry->actorId === 'actor-1' && $entry->referenceId === 'ref-1' && $entry->metadata['locationId'] === 'LOC-1' && $entry->quantity < 0;
        }));
        $this->ledgerRepository->expects($this->once())->method('appendAll')->with($this->callback(function (array $entries) {
            return count($entries) === 1 && $entries[0]->actorId === 'actor-1' && $entries[0]->referenceId === 'ref-1' && $entries[0]->metadata['locationId'] === 'LOC-1' && $entries[0]->quantity > 0;
        }));

        $this->journalService->expects($this->once())
            ->method('onKitDisassembly')
            ->with(
                'tenant-1',
                $this->isInstanceOf(\DateTimeImmutable::class),
                'KIT-1',
                2000,
                'ref-1'
            );

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteThrowsExceptionWhenComponentProductNotFound(): void
    {
        $expectedSku = 'KIT-1';
        $kit = new Kit('kit-id', $expectedSku, 'Test Kit');
        $kit->addComponent('comp-1', 2);

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU($expectedSku),
            'Test Kit',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(5)
        );

        $this->kitRepository->method('findBySku')->willReturn($kit);

        $this->productRepository->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($expectedSku) {
                return $sku->getValue() === $expectedSku;
            }))
            ->willReturn($kitProduct);

        $this->productRepository->method('findByIds')->willReturn([]);

        $this->ledgerRepository->method('currentQuantity')->willReturn(5);

        $kitLayer = new InventoryCostLayer('layer-1', 'prod_kit_1', 'tenant-1', 5, 2000, new \DateTimeImmutable(), 'ref-1');

        $this->costLayerRepository->method('getActiveLayers')->willReturnCallback(function($variantId) use ($kitLayer) {
            if ($variantId === 'prod_kit_1') {
                return [$kitLayer];
            }
            return [];
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Product variant comp-1 not found.");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteFallsBackToDefaultCostWhenGetActiveLayersThrowsException(): void
    {
        $expectedSku = 'KIT-1';
        $kit = new Kit('kit-id', $expectedSku, 'Test Kit');
        $kit->addComponent('comp-1', 2);

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU($expectedSku),
            'Test Kit',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(5)
        );

        $compProduct = Product::create(
            'comp-1',
            new SKU('COMP-1'),
            'Test Component 1',
            new Department('PARTS'),
            new LocationId('LOC-1'),
            new Quantity(10)
        );

        $this->kitRepository->method('findBySku')->willReturn($kit);

        $this->productRepository->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($expectedSku) {
                return $sku->getValue() === $expectedSku;
            }))
            ->willReturn($kitProduct);

        $this->productRepository->method('findByIds')
            ->with(['comp-1'])
            ->willReturn(['comp-1' => $compProduct]);

        $this->ledgerRepository->method('currentQuantity')->willReturn(5);

        $kitLayer = new InventoryCostLayer('layer-1', 'prod_kit_1', 'tenant-1', 5, 2000, new \DateTimeImmutable(), 'ref-1');

        $this->costLayerRepository->method('getActiveLayers')->willReturnCallback(function($variantId) use ($kitLayer) {
            if ($variantId === 'prod_kit_1') {
                return [$kitLayer];
            }
            throw new \Exception("Database error");
        });

        $this->costLayerRepository->expects($this->exactly(2))->method('saveBatch')->willReturnCallback(function (array $layers) {
            if (count($layers) > 0 && $layers[0]->variantId === 'prod_kit_1') {
                return; // This is the call from CostLayerService
            }
            $this->assertCount(1, $layers);
            $this->assertEquals(1000, $layers[0]->unitCostCents);
        });

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteThrowsExceptionWhenInsufficientCostLayers(): void
    {
        $this->productRepository->expects($this->never())->method('save');
        $this->ledgerRepository->expects($this->never())->method('append');
        $this->costLayerRepository->expects($this->never())->method('saveBatch');
        $this->costLayerRepository->expects($this->never())->method('save');
        $this->journalService->expects($this->never())->method('onKitDisassembly');

        $expectedSku = 'KIT-1';
        $kit = new Kit('kit-id', $expectedSku, 'Test Kit');

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU($expectedSku),
            'Test Kit',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(5)
        );

        $this->kitRepository->method('findBySku')->willReturn($kit);

        $this->productRepository->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($expectedSku) {
                return $sku->getValue() === $expectedSku;
            }))
            ->willReturn($kitProduct);

        $this->ledgerRepository->method('currentQuantity')->willReturn(5);

        // Simulation: Get active layers returns insufficient or empty array causing DomainException in CostLayerService
        $this->costLayerRepository->method('getActiveLayers')->willReturn([]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Insufficient cost layers to cover quantity 1");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteTakesFirstLayerCostWhenTotalUnitsZero(): void
    {
        $expectedSku = 'KIT-1';
        $kit = new Kit('kit-id', $expectedSku, 'Test Kit');
        $kit->addComponent('comp-1', 2);

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU($expectedSku),
            'Test Kit',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(5)
        );

        $compProduct = Product::create(
            'comp-1',
            new SKU('COMP-1'),
            'Test Component 1',
            new Department('PARTS'),
            new LocationId('LOC-1'),
            new Quantity(10)
        );

        $this->kitRepository->method('findBySku')->willReturn($kit);

        $this->productRepository->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($expectedSku) {
                return $sku->getValue() === $expectedSku;
            }))
            ->willReturn($kitProduct);

        $this->productRepository->method('findByIds')
            ->with(['comp-1'])
            ->willReturn(['comp-1' => $compProduct]);

        $this->ledgerRepository->method('currentQuantity')->willReturn(5);

        $kitLayer = new InventoryCostLayer('layer-1', 'prod_kit_1', 'tenant-1', 5, 2000, new \DateTimeImmutable(), 'ref-1');

        $compLayer = new InventoryCostLayer('layer-2', 'comp-1', 'tenant-1', 10, 500, new \DateTimeImmutable(), 'ref-1');
        $compLayer->setRemainingQuantity(0);

        $this->costLayerRepository->method('getActiveLayers')->willReturnCallback(function($variantId) use ($kitLayer, $compLayer) {
            if ($variantId === 'prod_kit_1') {
                return [$kitLayer];
            }
            return [$compLayer];
        });

        $this->costLayerRepository->expects($this->exactly(2))->method('saveBatch')->willReturnCallback(function (array $layers) {
            if (count($layers) > 0 && $layers[0]->variantId === 'prod_kit_1') {
                return; // This is the call from CostLayerService
            }
            $this->assertCount(1, $layers);
            // $kitLayer cost = 2000, 1 quantity disassembled => $totalDisassembledCost = 2000
            // $compLayer unit cost = 500 (used because totalUnits = 0), needed = 2
            // $totalEstimatedComponentsCost = 2 * 500 = 1000
            // $scaleFactor = 2000 / 1000 = 2
            // $allocatedUnitCost = 500 * 2 = 1000
            $this->assertEquals(1000, $layers[0]->unitCostCents);
        });

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }
}
