<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ForecastingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\DemandForecastRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\DemandForecast;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;

class ForecastingControllerTest extends TestCase
{
    private ForecastingController $controller;
    private $productRepo;
    private $ledgerRepo;
    private $replenishmentRuleRepo;
    private $demandForecastRepo;

    protected function setUp(): void
    {
        $this->controller = new ForecastingController();
        $this->productRepo = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepo = $this->createMock(LedgerRepositoryInterface::class);
        $this->replenishmentRuleRepo = $this->createMock(ReorderPolicyRepositoryInterface::class);
        $this->demandForecastRepo = $this->createMock(DemandForecastRepositoryInterface::class);
    }

    public function testGenerateForecastReturns200OnSuccess(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku' => 'TEST-SKU',
                'locationId' => 'LOC-1',
                'forecastDays' => 10,
                'trendMultiplier' => 1.5,
            ]);

        // Mock product lookup
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn(new SKU('TEST-SKU'));
        $locationStock = $this->createMock(\InventoryApp\Domain\Inventory\Entities\LocationStock::class);
        $locationStock->method('getStockQuantity')->willReturn(new Quantity(100));
        $product->method('getStockAt')->willReturn($locationStock);

        $this->productRepo->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        // Mock ledger entries lookup
        $this->ledgerRepo->expects($this->once())
            ->method('entriesFor')
            ->willReturn([]);

        // Mock forecast save
        $this->demandForecastRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(DemandForecast::class));

        // Capture error output to prevent polluting PHPUnit test output
        ob_start();
        $response = $this->controller->generateForecast(
            $request,
            $this->productRepo,
            $this->ledgerRepo,
            $this->replenishmentRuleRepo,
            $this->demandForecastRepo
        );
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        $this->assertEquals('Demand forecast generated successfully', $content['message']);
        $this->assertEquals('TEST-SKU', $content['forecast']['sku']);
        $this->assertEquals('LOC-1', $content['forecast']['locationId']);
    }

    public function testGenerateForecastReturns400OnValidationError(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed'));

        $response = $this->controller->generateForecast(
            $request,
            $this->productRepo,
            $this->ledgerRepo,
            $this->replenishmentRuleRepo,
            $this->demandForecastRepo
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = $response->getContent();
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        $this->assertEquals('Validation failed', $content['error']);
    }

    public function testGenerateForecastReturns500OnGenericException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('validate')
            ->willThrowException(new \Exception('Database error'));

        // Capture error output to prevent polluting PHPUnit test output
        ob_start();
        $response = $this->controller->generateForecast(
            $request,
            $this->productRepo,
            $this->ledgerRepo,
            $this->replenishmentRuleRepo,
            $this->demandForecastRepo
        );
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = $response->getContent();
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
