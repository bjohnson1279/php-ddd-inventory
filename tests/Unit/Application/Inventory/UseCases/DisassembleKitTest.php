<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\DisassembleKit;
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

class DisassembleKitTest extends TestCase
{
    private $kitRepository;
    private $productRepository;
    private $ledgerRepository;
    private $costLayerRepository;
    private $journalService;
    private $useCase;

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

    public function testExecuteThrowsExceptionWhenQuantityIsZeroOrLess(): void
    {
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
        $kit = new Kit('kit-id', 'KIT-1', 'Test Kit');

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU('KIT-1'),
            'Test Kit',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(0)
        );

        $this->kitRepository->method('findBySku')->willReturn($kit);
        $this->productRepository->method('findBySku')->willReturn($kitProduct);
        $this->ledgerRepository->method('currentQuantity')->willReturn(0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Insufficient stock for Kit variant KIT-1. Needed: 1, Available: 0");

        $this->useCase->execute([
            'tenantId' => 'tenant-1',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);
    }

    public function testExecuteSuccessfullyDisassemblesKit(): void
    {
        $kit = new Kit('kit-id', 'KIT-1', 'Test Kit');
        $kit->addComponent('comp-1', 2);

        $kitProduct = Product::create(
            'prod_kit_1',
            new SKU('KIT-1'),
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
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'KIT-1';
            }))
            ->willReturn($kitProduct);

        $this->productRepository->method('findById')->willReturnMap([
            ['comp-1', $compProduct]
        ]);

        $this->ledgerRepository->method('currentQuantity')->willReturn(5);

        $kitLayer = new InventoryCostLayer('layer-1', 'prod_kit_1', 'tenant-1', 5, 2000, new \DateTimeImmutable(), 'ref-1');
        $compLayer = new InventoryCostLayer('layer-2', 'comp-1', 'tenant-1', 10, 500, new \DateTimeImmutable(), 'ref-1');

        $this->costLayerRepository->method('getActiveLayers')->willReturnMap([
            ['prod_kit_1', 'received_at ASC', [$kitLayer]],
            ['comp-1', 'received_at ASC', [$compLayer]]
        ]);

        $this->costLayerRepository->expects($this->atLeastOnce())->method('saveBatch');
        $this->costLayerRepository->expects($this->atLeastOnce())->method('save');

        $this->productRepository->expects($this->exactly(2))->method('save');
        $this->ledgerRepository->expects($this->exactly(2))->method('append');

        $this->journalService->expects($this->once())->method('onKitDisassembly');

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
