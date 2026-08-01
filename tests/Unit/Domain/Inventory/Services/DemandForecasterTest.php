<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inventory\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Services\DemandForecaster;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\DemandForecastRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Entities\LocationStock;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Procurement\Services\ReorderPolicyService;
use InventoryApp\Infrastructure\ServiceContainer;
use Exception;

final class DemandForecasterTest extends TestCase
{
    private ProductRepositoryInterface $productRepo;
    private LedgerRepositoryInterface $ledgerRepo;
    private ReorderPolicyRepositoryInterface $policyRepo;
    private DemandForecastRepositoryInterface $forecastRepo;
    private DemandForecaster $forecaster;

    protected function setUp(): void
    {
        $this->productRepo = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepo = $this->createMock(LedgerRepositoryInterface::class);
        $this->policyRepo = $this->createMock(ReorderPolicyRepositoryInterface::class);
        $this->forecastRepo = $this->createMock(DemandForecastRepositoryInterface::class);

        $this->forecaster = new DemandForecaster(
            $this->productRepo,
            $this->ledgerRepo,
            $this->policyRepo,
            $this->forecastRepo
        );
    }

    public function testGenerateDemandForecastErrorPath(): void
    {
        $sku = new SKU('TEST-SKU');
        $locationId = new LocationId('LOC-1');

        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn($sku);

        $locationStock = $this->createMock(LocationStock::class);
        $stockQty = $this->createMock(Quantity::class);
        $stockQty->method('getValue')->willReturn(10);
        $locationStock->method('getStockQuantity')->willReturn($stockQty);

        $product->method('getStockAt')->willReturn($locationStock);

        $this->ledgerRepo->method('entriesFor')->willReturn([]);

        // Use a mock ServiceContainer that binds the ReorderPolicyService mock
        $mockPolicyService = $this->createMock(ReorderPolicyService::class);
        $mockPolicyService->method('checkPolicy')->willThrowException(new Exception('Policy evaluation failed'));

        $container = ServiceContainer::getInstance();
        $container->instance(\InventoryApp\Domain\Procurement\Services\ReorderPolicyService::class, $mockPolicyService);

        // Run forecast generation
        // Suppress error_log output for clean tests
        ob_start();
        $forecast = $this->forecaster->generateDemandForecast($sku, $locationId, 30, 1.0, $product);
        ob_end_clean();

        // Check if forecast is valid
        $this->assertNotNull($forecast);
        $this->assertEquals($sku, $forecast->sku);
        $this->assertEquals($locationId, $forecast->locationId);

        // Clean up mock
        $container->forgetInstance(\InventoryApp\Domain\Procurement\Services\ReorderPolicyService::class);
    }
}
