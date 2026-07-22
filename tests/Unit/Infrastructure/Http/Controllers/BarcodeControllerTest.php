<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\BarcodeController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Infrastructure\Http\Response;

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
    public function testLookupReturns400WhenValueIsEmpty(): void
    {
        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('value')
            ->willReturn('');

        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Barcode value query parameter is required', $response->getContent());
    }

    public function testLookupReturns200WithVariantIdOnSuccess(): void
    {
        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('value')
            ->willReturn('1234567890');

        $this->repoMock->expects($this->once())
            ->method('findVariantByBarcodeValue')
            ->with('1234567890')
            ->willReturn('var-123');

        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('var-123', $content['variant_id'] ?? '');
    }

    public function testLookupReturns400OnDomainException(): void
    {
        $this->requestMock->method('query')->willReturn('123');

        $this->repoMock->method('findVariantByBarcodeValue')
            ->willThrowException(new \DomainException('Variant not found'));

        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Variant not found', $response->getContent());
    }

    public function testLookupReturns500OnUnexpectedException(): void
    {
        $this->requestMock->method('query')->willReturn('123');

        $this->repoMock->method('findVariantByBarcodeValue')
            ->willThrowException(new \Exception('Database error'));

        $response = $this->controller->lookup($this->requestMock, $this->repoMock);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }
    public function testAssignReturns201OnSuccess(): void
    {
        $this->requestMock->expects($this->any())
            ->method('validate')
            ->willReturnCallback(function ($rules) {
                if (isset($rules['variant_id'])) {
                    return [
                        'variant_id' => 'var-123',
                        'value'      => '123456789012',
                        'symbology'  => 'upc_a',
                        'source'     => 'supplier',
                    ];
                }
                return ['is_primary' => false];
            });

        $this->requestMock->method('query')->with('is_primary')->willReturn(null);

        $this->repoMock->expects($this->once())
            ->method('registerAssignment')
            ->with(
                'var-123',
                $this->callback(function ($barcode) {
                    return $barcode->value === '123456789012' && $barcode->symbology->value === 'upc_a';
                }),
                $this->callback(function ($source) {
                    return $source->value === 'supplier';
                }),
                false
            );

        $response = $this->controller->assign($this->requestMock, $this->repoMock);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('Barcode assigned successfully', $response->getContent());
    }

    public function testAssignReturns400OnInvalidSymbology(): void
    {
        $this->requestMock->expects($this->any())
            ->method('validate')
            ->willReturnCallback(function ($rules) {
                if (isset($rules['variant_id'])) {
                    return [
                        'variant_id' => 'var-123',
                        'value'      => '123456789012',
                        'symbology'  => 'INVALID_SYM',
                        'source'     => 'supplier',
                    ];
                }
                return ['is_primary' => false];
            });

        $this->requestMock->method('query')->willReturn(null);

        $response = $this->controller->assign($this->requestMock, $this->repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Invalid barcode symbology', $response->getContent());
    }

    public function testAssignReturns400OnDomainException(): void
    {
        $this->requestMock->expects($this->any())
            ->method('validate')
            ->willReturnCallback(function ($rules) {
                if (isset($rules['variant_id'])) {
                    return [
                        'variant_id' => 'var-123',
                        'value'      => '123456789012',
                        'symbology'  => 'upc_a',
                        'source'     => 'supplier',
                    ];
                }
                return ['is_primary' => false];
            });
        $this->requestMock->method('query')->willReturn(null);

        $this->repoMock->method('registerAssignment')
            ->willThrowException(new \DomainException('Assignment failed'));

        $response = $this->controller->assign($this->requestMock, $this->repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Assignment failed', $response->getContent());
    }

    public function testAssignReturns500OnUnexpectedException(): void
    {
        $this->requestMock->expects($this->any())
            ->method('validate')
            ->willReturnCallback(function ($rules) {
                if (isset($rules['variant_id'])) {
                    return [
                        'variant_id' => 'var-123',
                        'value'      => '123456789012',
                        'symbology'  => 'upc_a',
                        'source'     => 'supplier',
                    ];
                }
                return ['is_primary' => false];
            });
        $this->requestMock->method('query')->willReturn(null);

        $this->repoMock->method('registerAssignment')
            ->willThrowException(new \Exception('DB failure'));

        $response = $this->controller->assign($this->requestMock, $this->repoMock);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }
    public function testGetVariantSetReturns200OnSuccess(): void
    {
        require_once __DIR__ . '/../../../../../src/Domain/Barcode/Aggregates/VariantBarcodeSet.php';

        $barcode = new \InventoryApp\Domain\Barcode\ValueObjects\Barcode(
            \InventoryApp\Domain\Barcode\Enums\BarcodeSymbology::UPC_A,
            '123456789012'
        );

        $assignment = new \InventoryApp\Domain\Barcode\Aggregates\BarcodeAssignment(
            'asg-1',
            'var-123',
            $barcode,
            \InventoryApp\Domain\Barcode\Enums\BarcodeSource::Supplier,
            true,
            new \DateTimeImmutable('2023-01-01T12:00:00+00:00')
        );

        $set = $this->createMock(\InventoryApp\Domain\Barcode\Aggregates\VariantBarcodeSet::class);
        $set->method('all')->willReturn([$assignment]);

        $this->repoMock->expects($this->once())
            ->method('findSetForVariant')
            ->with('var-123')
            ->willReturn($set);

        $response = $this->controller->getVariantSet($this->requestMock, 'var-123', $this->repoMock);

        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('var-123', $content['variant_id']);
        $this->assertCount(1, $content['assignments']);
        $this->assertEquals('123456789012', $content['assignments'][0]['value']);
        $this->assertEquals('upc_a', $content['assignments'][0]['symbology']);
    }

    public function testGetVariantSetReturns400OnDomainException(): void
    {
        $this->repoMock->method('findSetForVariant')
            ->willThrowException(new \DomainException('Variant set not found'));

        $response = $this->controller->getVariantSet($this->requestMock, 'var-123', $this->repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Variant set not found', $response->getContent());
    }

    public function testGetVariantSetReturns500OnUnexpectedException(): void
    {
        $this->repoMock->method('findSetForVariant')
            ->willThrowException(new \Exception('Database connection failed'));

        $response = $this->controller->getVariantSet($this->requestMock, 'var-123', $this->repoMock);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }
    public function testScanReturns400OnValidationException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed: Missing rawScan'));

        $response = $this->controller->scan($this->requestMock, $this->repoMock, 'tenant-123');

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    public function testScanReturns500OnUnexpectedException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \Exception('Redis connection lost'));

        $response = $this->controller->scan($this->requestMock, $this->repoMock, 'tenant-123');

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }
}
