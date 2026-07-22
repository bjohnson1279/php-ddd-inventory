<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\BarcodeController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use Exception;
use DomainException;
use InvalidArgumentException;
use ValidationException;

class BarcodeControllerTest extends TestCase
{
    private BarcodeController $controller;
    private $repoMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->controller = new BarcodeController();
        $this->repoMock = $this->createMock(BarcodeRepositoryInterface::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
    }

    public function testLookupWithValidValueReturns200(): void
    {
        $this->requestMock->method('query')
            ->with('value')
            ->willReturn('123456789');

        $this->repoMock->expects($this->once())
            ->method('findVariantByBarcodeValue')
            ->with('123456789')
            ->willReturn('VAR-123');

        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(json_encode(['variant_id' => 'VAR-123']), $response->getContent());
    }

    public function testLookupWithoutValueReturns400(): void
    {
        $this->requestMock->method('query')
            ->with('value')
            ->willReturn('');

        $this->repoMock->expects($this->never())
            ->method('findVariantByBarcodeValue');

        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(json_encode(['error' => 'Barcode value query parameter is required']), $response->getContent());
    }

    public function testLookupThrowsDomainExceptionReturns400(): void
    {
        $this->requestMock->method('query')
            ->with('value')
            ->willReturn('123456789');

        $this->repoMock->method('findVariantByBarcodeValue')
            ->with('123456789')
            ->willThrowException(new DomainException('Variant not found'));

        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(json_encode(['error' => 'Variant not found']), $response->getContent());
    }

    public function testLookupThrowsExceptionReturns500(): void
    {
        $this->requestMock->method('query')
            ->with('value')
            ->willReturn('123456789');

        $this->repoMock->method('findVariantByBarcodeValue')
            ->with('123456789')
            ->willThrowException(new Exception('Database connection failed'));

        // Output buffering to catch the error_log
        // Suppression of stderr isn't possible with ob_start(), but the error logs to console as expected
        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals(json_encode(['error' => 'An internal server error occurred.']), $response->getContent());
    }
}
