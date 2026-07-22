<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\InventoryController;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\TransferStock;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use InventoryApp\Application\Inventory\UseCases\AllocateStock;
use Exception;

class InventoryControllerTest extends TestCase
{
    private InventoryController $controller;
    private $receiveStockMock;
    private $dispatchStockMock;
    private $transferStockMock;
    private $getStockLevelMock;
    private $allocateStockMock;

    protected function setUp(): void
    {
        $this->controller = new InventoryController();
        $this->receiveStockMock = $this->createMock(ReceiveStock::class);
        $this->dispatchStockMock = $this->createMock(DispatchStock::class);
        $this->transferStockMock = $this->createMock(TransferStock::class);
        $this->getStockLevelMock = $this->createMock(\InventoryApp\Application\Inventory\Queries\StockQueryServiceInterface::class);
        $this->allocateStockMock = $this->createMock(AllocateStock::class);
    }

    /**
     * Test: receive() successfully processes a valid receive stock request
     */
    public function testReceiveStockWithValidRequest(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'location_id' => 'required|string'
            ])
            ->willReturn([
                'sku' => 'TSHIRT-L-RED',
                'quantity' => 10,
                'location_id' => 'LOC-STOREFRONT'
            ]);

        $this->receiveStockMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(fn($sku) => $sku instanceof \InventoryApp\Domain\Inventory\ValueObjects\SKU && $sku->getValue() === 'TSHIRT-L-RED'),
                $this->callback(fn($loc) => $loc instanceof \InventoryApp\Domain\Inventory\ValueObjects\LocationId && $loc->getValue() === 'LOC-STOREFRONT'),
                $this->callback(fn($qty) => $qty instanceof \InventoryApp\Domain\Inventory\ValueObjects\Quantity && $qty->getValue() === 10)
            );

        $response = $this->controller->receive($requestMock, $this->receiveStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('Stock received successfully', $response->getContent());
    }

    /**
     * Test: receive() returns 400 when validation fails
     */
    public function testReceiveStockWithMissingRequiredFields(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed: Missing required field'));

        $response = $this->controller->receive($requestMock, $this->receiveStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    /**
     * Test: receive() returns 400 when use case throws exception
     */
    public function testReceiveStockWhenUseCaseThrowsException(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku' => 'NONEXISTENT-SKU',
                'quantity' => 5,
                'location_id' => 'LOC-STOREFRONT'
            ]);

        $this->receiveStockMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \DomainException('Product not found with SKU: NONEXISTENT-SKU'));

        $response = $this->controller->receive($requestMock, $this->receiveStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Product not found', $response->getContent());
    }

    /**
     * Test: receive() returns 400 when quantity is below minimum (0)
     */
    public function testReceiveStockWithInvalidQuantity(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed: Quantity must be at least 1'));

        $response = $this->controller->receive($requestMock, $this->receiveStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Quantity must be at least 1', $response->getContent());
    }

    /**
     * Test: dispatch() successfully processes a valid dispatch stock request
     */
    public function testDispatchStockWithValidRequest(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'location_id' => 'required|string'
            ])
            ->willReturn([
                'sku' => 'WIDGET-001',
                'quantity' => 3,
                'location_id' => 'LOC-BACKROOM'
            ]);

        $this->dispatchStockMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(fn($sku) => $sku instanceof \InventoryApp\Domain\Inventory\ValueObjects\SKU && $sku->getValue() === 'WIDGET-001'),
                $this->callback(fn($loc) => $loc instanceof \InventoryApp\Domain\Inventory\ValueObjects\LocationId && $loc->getValue() === 'LOC-BACKROOM'),
                $this->callback(fn($qty) => $qty instanceof \InventoryApp\Domain\Inventory\ValueObjects\Quantity && $qty->getValue() === 3)
            );

        $response = $this->controller->dispatch($requestMock, $this->dispatchStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Stock dispatched successfully', $response->getContent());
    }

    /**
     * Test: dispatch() returns 400 when validation fails
     */
    public function testDispatchStockWithMissingRequiredFields(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed: Missing location_id'));

        $response = $this->controller->dispatch($requestMock, $this->dispatchStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    /**
     * Test: dispatch() returns 400 when insufficient stock exception is thrown
     */
    public function testDispatchStockWithInsufficientStock(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku' => 'ITEM-100',
                'quantity' => 1000,
                'location_id' => 'LOC-STOREFRONT'
            ]);

        $this->dispatchStockMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \DomainException('Insufficient stock at location'));

        $response = $this->controller->dispatch($requestMock, $this->dispatchStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Insufficient stock', $response->getContent());
    }

    /**
     * Test: transfer() successfully processes a valid transfer stock request
     */
    public function testTransferStockWithValidRequest(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'sku'             => 'required|string',
                'from_location'   => 'required|string',
                'to_location'     => 'required|string',
                'quantity'        => 'required|integer|min:1',
            ])
            ->willReturn([
                'sku' => 'WIDGET-002',
                'from_location' => 'LOC-BACKROOM',
                'to_location' => 'LOC-STOREFRONT',
                'quantity' => 5
            ]);

        $this->transferStockMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(fn($sku) => $sku instanceof \InventoryApp\Domain\Inventory\ValueObjects\SKU && $sku->getValue() === 'WIDGET-002'),
                $this->callback(fn($from) => $from instanceof \InventoryApp\Domain\Inventory\ValueObjects\LocationId && $from->getValue() === 'LOC-BACKROOM'),
                $this->callback(fn($to) => $to instanceof \InventoryApp\Domain\Inventory\ValueObjects\LocationId && $to->getValue() === 'LOC-STOREFRONT'),
                $this->callback(fn($qty) => $qty instanceof \InventoryApp\Domain\Inventory\ValueObjects\Quantity && $qty->getValue() === 5)
            );

        $response = $this->controller->transfer($requestMock, $this->transferStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Stock transferred successfully', $response->getContent());
    }

    /**
     * Test: transfer() returns 400 when validation fails
     */
    public function testTransferStockWithMissingRequiredFields(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed: Missing to_location'));

        $response = $this->controller->transfer($requestMock, $this->transferStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    /**
     * Test: transfer() returns 400 when domain exception is thrown
     */
    public function testTransferStockWithDomainException(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku' => 'ITEM-101',
                'from_location' => 'LOC-A',
                'to_location' => 'LOC-B',
                'quantity' => 10
            ]);

        $this->transferStockMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \DomainException('Insufficient stock to transfer'));

        $response = $this->controller->transfer($requestMock, $this->transferStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Insufficient stock to transfer', $response->getContent());
    }

    /**
     * Test: stockLevel() returns total stock when no location is specified
     */
    public function testGetStockLevelForAllLocations(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('location_id')
            ->willReturn(null);

        $this->getStockLevelMock->expects($this->once())
            ->method('getStockLevel')
            ->with('TSHIRT-L-RED', null)
            ->willReturn(new \InventoryApp\Application\Inventory\Queries\StockLevelDTO('TSHIRT-L-RED', 'ALL', 50));

        $response = $this->controller->stockLevel($requestMock, 'TSHIRT-L-RED', $this->getStockLevelMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('TSHIRT-L-RED', $content);
        $this->assertStringContainsString('50', $content);
        $this->assertStringContainsString('ALL', $content);
    }

    /**
     * Test: stockLevel() returns stock for a specific location
     */
    public function testGetStockLevelForSpecificLocation(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('location_id')
            ->willReturn('LOC-WAREHOUSE');

        $this->getStockLevelMock->expects($this->once())
            ->method('getStockLevel')
            ->with('WIDGET-100', 'LOC-WAREHOUSE')
            ->willReturn(new \InventoryApp\Application\Inventory\Queries\StockLevelDTO('WIDGET-100', 'LOC-WAREHOUSE', 25));

        $response = $this->controller->stockLevel($requestMock, 'WIDGET-100', $this->getStockLevelMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('WIDGET-100', $content);
        $this->assertStringContainsString('25', $content);
        $this->assertStringContainsString('LOC-WAREHOUSE', $content);
    }

    /**
     * Test: stockLevel() returns 404 when product not found
     */
    public function testGetStockLevelForNonexistentProduct(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('location_id')
            ->willReturn(null);

        $this->getStockLevelMock->expects($this->once())
            ->method('getStockLevel')
            ->with('NONEXISTENT-SKU', null)
            ->willThrowException(new \DomainException('Product not found with SKU: NONEXISTENT-SKU'));

        $response = $this->controller->stockLevel($requestMock, 'NONEXISTENT-SKU', $this->getStockLevelMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Product not found', $response->getContent());
    }

    /**
     * Test: stockLevel() returns 404 when use case throws generic exception
     */
    public function testGetStockLevelWithException(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('location_id')
            ->willReturn('LOC-INVALID');

        $this->getStockLevelMock->expects($this->once())
            ->method('getStockLevel')
            ->willThrowException(new \DomainException('Invalid location identifier'));

        $response = $this->controller->stockLevel($requestMock, 'ITEM-001', $this->getStockLevelMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Invalid location', $response->getContent());
    }

    /**
     * Test: stockLevel() returns zero stock correctly
     */
    public function testGetStockLevelReturnsZeroStock(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->willReturn('LOC-STOREFRONT');

        $this->getStockLevelMock->expects($this->once())
            ->method('getStockLevel')
            ->with('OUT-OF-STOCK-ITEM', 'LOC-STOREFRONT')
            ->willReturn(new \InventoryApp\Application\Inventory\Queries\StockLevelDTO('OUT-OF-STOCK-ITEM', 'LOC-STOREFRONT', 0));

        $response = $this->controller->stockLevel($requestMock, 'OUT-OF-STOCK-ITEM', $this->getStockLevelMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('0', $response->getContent());
    }
    /**
     * Test: allocate() successfully processes a valid allocate stock request
     */
    public function testAllocateStockWithValidRequest(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'sku'         => 'required|string',
                'amount'      => 'required|integer',
                'location_id' => 'string'
            ])
            ->willReturn([
                'sku'         => 'WIDGET-200',
                'amount'      => 5,
                'location_id' => 'LOC-BACKROOM'
            ]);

        $this->allocateStockMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(fn($sku) => $sku instanceof \InventoryApp\Domain\Inventory\ValueObjects\SKU && $sku->getValue() === 'WIDGET-200'),
                $this->callback(fn($qty) => $qty instanceof \InventoryApp\Domain\Inventory\ValueObjects\Quantity && $qty->getValue() === 5),
                $this->callback(fn($loc) => $loc instanceof \InventoryApp\Domain\Inventory\ValueObjects\LocationId && $loc->getValue() === 'LOC-BACKROOM')
            );

        $response = $this->controller->allocate($requestMock, $this->allocateStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Stock allocated successfully', $response->getContent());
    }
    /**
     * Test: allocate() returns 400 when validation fails
     */
    public function testAllocateStockWithMissingRequiredFields(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed: Missing required field'));

        $response = $this->controller->allocate($requestMock, $this->allocateStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }
    /**
     * Test: allocate() returns 400 when use case throws exception
     */
    public function testAllocateStockWhenUseCaseThrowsException(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku'         => 'NONEXISTENT-SKU',
                'amount'      => 5,
                'location_id' => 'LOC-STOREFRONT'
            ]);

        $this->allocateStockMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \DomainException('Product not found with SKU: NONEXISTENT-SKU'));

        $response = $this->controller->allocate($requestMock, $this->allocateStockMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Product not found', $response->getContent());
    }
}
