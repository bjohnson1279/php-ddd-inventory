<?php

namespace Tests\Unit\Domain\Procurement\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Procurement\Services\ReorderPointForecaster;
use InventoryApp\Domain\Procurement\Services\DemandVelocityCalculator;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use DateTime;
use Ramsey\Uuid\Uuid;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;

class ReorderPointForecasterTest extends TestCase
{
    private $velocityCalculatorMock;
    private $productRepoMock;
    private $poRepoMock;
    private $forecaster;

    protected function setUp(): void
    {
        $this->velocityCalculatorMock = $this->createMock(DemandVelocityCalculator::class);
        $this->productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $this->poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);

        $this->forecaster = new ReorderPointForecaster(
            $this->velocityCalculatorMock,
            $this->productRepoMock,
            $this->poRepoMock
        );
    }

    private function createDummyProduct(string $skuStr): Product
    {
        return new Product(
            'prod-1',
            new SKU($skuStr),
            'Test Product',
            new Department('Electronics'),
            new Quantity(10)
        );
    }

    public function testForecastReorderPointWithoutTenantId()
    {
        $this->velocityCalculatorMock->expects($this->once())
            ->method('calculateDailySalesStats')
            ->willReturn(['average' => 10.0, 'stdDev' => 2.0]);

        $product = $this->createDummyProduct('SKU-1');

        $result = $this->forecaster->forecastReorderPoint(
            'SKU-1',
            'LOC-1',
            5,
            20,
            30,
            null,
            $product
        );

        // leadTimeDaysAvg = 5, stdDevSales = 2, meanSales = 10, leadTimeDaysStdDev = 0
        // term1 = 5 * (2^2) = 20
        // term2 = (10^2) * (0^2) = 0
        // calculatedSafetyStock = 1.65 * sqrt(20) = 7.379
        // finalSafetyStock = 7.379 (since > 0)
        // rawRop = 10 * 5 + 7.379 = 57.379
        // ceil(57.379) = 58

        $this->assertEquals(58, $result);
    }
}
