<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Application\Shipping\UseCases\RouteOrder;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use PHPUnit\Framework\TestCase;
use Exception;

class RouteOrderTest extends TestCase
{
    private ProductRepositoryInterface $productRepositoryMock;
    private CarrierServiceInterface $carrierServiceMock;
    private RouteOrder $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->carrierServiceMock = $this->createMock(CarrierServiceInterface::class);
        $this->useCase = new RouteOrder(
            $this->productRepositoryMock,
            $this->carrierServiceMock
        );
    }

    public function test_it_returns_fallback_cost_when_carrier_service_throws_exception(): void
    {
        $skuStr = 'TEST-SKU';
        $quantity = 1;
        $destinationAddress = '123 Main St, New York, NY 10001';

        // Setup fake product with enough stock
        $product = new Product('p1', new SKU($skuStr), 'Test Product', new Department('TEST'));
        $locationId = new LocationId('LOC-WH1-NY');
        // Add sufficient stock so allocation works
        $product->getStockAt($locationId)->addStock(new Quantity(5), new Condition(Condition::NEW));

        $this->productRepositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(fn(SKU $s) => $s->getValue() === $skuStr))
            ->willReturn($product);

        // Carrier service throws exception when fetching rates
        $this->carrierServiceMock->expects($this->once())
            ->method('fetchRates')
            ->willThrowException(new Exception('Carrier service failure'));

        $fulfillmentPlan = $this->useCase->execute($skuStr, $quantity, $destinationAddress);

        // Assert fallback cost is used
        $this->assertEquals(999999, $fulfillmentPlan->estimatedShippingCostCents);
    }
}
