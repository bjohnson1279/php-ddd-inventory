<?php

namespace Tests\Unit\Domain\Barcode\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Barcode\Services\BarcodeScanDispatcher;
use InventoryApp\Domain\Barcode\Services\ScanContext;
use InventoryApp\Domain\Barcode\Services\IScanHandler;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use DomainException;
use InvalidArgumentException;

class BarcodeScanDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        class_exists(BarcodeScanDispatcher::class);
    }

    public function testDispatchSuccessWithRegisteredHandler(): void
    {
        $repo = $this->createMock(BarcodeRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findSkuByBarcodeValue')
            ->with('BARCODE-123')
            ->willReturn('SKU-ABC-1');

        $handler = $this->createMock(IScanHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with('SKU-ABC-1', 'barcode-123', ['location_id' => 'loc-1']);

        $dispatcher = new BarcodeScanDispatcher($repo);
        $dispatcher->register(ScanContext::PointOfSale, $handler);

        $resultSku = $dispatcher->dispatch('barcode-123', 'pos', ['location_id' => 'loc-1']);

        $this->assertEquals('SKU-ABC-1', $resultSku);
    }

    public function testDispatchThrowsExceptionWhenBarcodeNotFound(): void
    {
        $repo = $this->createMock(BarcodeRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findSkuByBarcodeValue')
            ->with('UNKNOWN-BARCODE')
            ->willReturn(null);

        $dispatcher = new BarcodeScanDispatcher($repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No variant found for barcode: UNKNOWN-BARCODE');

        $dispatcher->dispatch('UNKNOWN-BARCODE', 'pos');
    }

    public function testDispatchThrowsExceptionForInvalidContext(): void
    {
        $repo = $this->createMock(BarcodeRepositoryInterface::class);
        $dispatcher = new BarcodeScanDispatcher($repo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scan context: invalid_context');

        $dispatcher->dispatch('BARCODE-123', 'invalid_context');
    }
}
