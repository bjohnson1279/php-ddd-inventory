<?php

<<<<<<< HEAD
=======
declare(strict_types=1);

>>>>>>> origin/master
namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ForecastingController;
<<<<<<< HEAD
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
=======
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
>>>>>>> origin/master
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\DemandForecastRepositoryInterface;
<<<<<<< HEAD
use InventoryApp\Domain\Inventory\Entities\DemandForecast;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
=======
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use DomainException;
>>>>>>> origin/master

class ForecastingControllerTest extends TestCase
{
    private ForecastingController $controller;
<<<<<<< HEAD
    private $productRepo;
    private $ledgerRepo;
    private $replenishmentRuleRepo;
    private $demandForecastRepo;
=======
    private $requestMock;
    private $productRepoMock;
    private $ledgerRepoMock;
    private $replenishmentRuleRepoMock;
    private $demandForecastRepoMock;
    private static bool $capsuleBooted = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$capsuleBooted) {
            $capsule = new Capsule;
            $capsule->addConnection([
                'driver'    => 'sqlite',
                'database'  => ':memory:',
                'prefix'    => '',
            ]);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            Capsule::schema()->create('products', function ($table) {
                $table->string('id');
                $table->string('sku');
            });
            Capsule::schema()->create('product_locations', function ($table) {
                $table->string('product_id');
                $table->string('location_id');
            });

            self::$capsuleBooted = true;
        }
    }

    protected function tearDown(): void
    {
        Capsule::table('products')->delete();
        Capsule::table('product_locations')->delete();
    }
>>>>>>> origin/master

    protected function setUp(): void
    {
        $this->controller = new ForecastingController();
<<<<<<< HEAD
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

=======
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepoMock = $this->createMock(LedgerRepositoryInterface::class);
        $this->replenishmentRuleRepoMock = $this->createMock(ReorderPolicyRepositoryInterface::class);
        $this->demandForecastRepoMock = $this->createMock(DemandForecastRepositoryInterface::class);
    }

    public function testGetReportHappyPath(): void
    {
        // 1. Setup DB state so DemandForecaster can find the product
        Capsule::table('products')->insert(['id' => 'prod-1', 'sku' => 'TEST-SKU-1']);
        Capsule::table('product_locations')->insert(['product_id' => 'prod-1', 'location_id' => 'LOC-TEST-1']);

        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('locationId', 'default')
            ->willReturn('LOC-TEST-1');

        // 2. Mock Product Repository
        $productMock = $this->createMock(\InventoryApp\Domain\Inventory\Entities\Product::class);
        $productMock->method('getSku')->willReturn(new SKU('TEST-SKU-1'));

        // Mock getStockAt to return a stock location with quantity
        $stockMock = $this->createMock(\InventoryApp\Domain\Inventory\Entities\LocationStock::class);
        $qtyMock = $this->createMock(\InventoryApp\Domain\Inventory\ValueObjects\Quantity::class);
        $qtyMock->method('getValue')->willReturn(150);
        $stockMock->method('getStockQuantity')->willReturn($qtyMock);
        $productMock->method('getStockAt')->willReturn($stockMock);

        $this->productRepoMock->expects($this->once())
            ->method('findBySkus')
            ->willReturn(['TEST-SKU-1' => $productMock]);

        // 3. Mock Repositories
        $this->demandForecastRepoMock->expects($this->once())
            ->method('findAllForLocation')
            ->willReturn([]);

        $this->replenishmentRuleRepoMock->expects($this->once())
            ->method('findAllByLocation')
            ->willReturn([]);

        $this->ledgerRepoMock->expects($this->once())
            ->method('entriesForSkusAndLocation')
            ->willReturn([]);

        $this->replenishmentRuleRepoMock->expects($this->once())
            ->method('findBySkusAndLocation')
            ->willReturn([]);

        // 4. Act
        $response = $this->controller->getReport(
            $this->requestMock,
            $this->productRepoMock,
            $this->ledgerRepoMock,
            $this->replenishmentRuleRepoMock,
            $this->demandForecastRepoMock
        );

        // 5. Assert
>>>>>>> origin/master
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
<<<<<<< HEAD
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
=======
        $this->assertStringContainsString('TEST-SKU-1', $content);
        $this->assertStringContainsString('LOC-TEST-1', $content);
        $this->assertStringContainsString('150', $content);
    }

    public function testGetReportReturns400OnDomainException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('query')
            ->willThrowException(new DomainException('Domain error occurred'));

        $response = $this->controller->getReport(
            $this->requestMock,
            $this->productRepoMock,
            $this->ledgerRepoMock,
            $this->replenishmentRuleRepoMock,
            $this->demandForecastRepoMock
>>>>>>> origin/master
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
<<<<<<< HEAD

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
=======
        $this->assertStringContainsString('Domain error occurred', $response->getContent());
    }

    public function testGetReportReturns400OnInvalidArgumentException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('locationId', 'default')
            ->willReturn('invalid-location-format'); // Causes InvalidArgumentException in LocationId

        $response = $this->controller->getReport(
            $this->requestMock,
            $this->productRepoMock,
            $this->ledgerRepoMock,
            $this->replenishmentRuleRepoMock,
            $this->demandForecastRepoMock
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('error', $response->getContent());
    }

    public function testGetReportReturns500OnGenericException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('query')
            ->willThrowException(new Exception('Unexpected database error'));

        $response = $this->controller->getReport(
            $this->requestMock,
            $this->productRepoMock,
            $this->ledgerRepoMock,
            $this->replenishmentRuleRepoMock,
            $this->demandForecastRepoMock
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
>>>>>>> origin/master
    }
}
