<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ReorderPolicyController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InvalidArgumentException;
use Exception;

class ReorderPolicyControllerTest extends TestCase
{
    private $controller;
    private $requestMock;
    private $repoMock;

    protected function setUp(): void
    {
        $this->controller = new ReorderPolicyController();
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->repoMock = $this->createMock(ReorderPolicyRepositoryInterface::class);
    }

    public function testCreateOrUpdateReturns200OnSuccess(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku'                 => 'SKU-123',
                'locationId'          => 'LOC-1',
                'reorderPoint'        => 10,
                'reorderQuantity'     => 50,
                'safetyStock'         => 5,
                'dynamicRopEnabled'   => true
            ]);

        $this->repoMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReorderPolicy $policy) {
                return $policy->sku->getValue() === 'SKU-123'
                    && $policy->locationId === 'LOC-1'
                    && $policy->reorderPoint === 10
                    && $policy->reorderQuantity === 50
                    && $policy->safetyStock === 5
                    && $policy->dynamicRopEnabled === true;
            }));

        $response = $this->controller->createOrUpdate($this->requestMock, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('SKU-123', $content['sku']);
        $this->assertEquals('LOC-1', $content['locationId']);
        $this->assertEquals(10, $content['reorderPoint']);
        $this->assertEquals(50, $content['reorderQuantity']);
        $this->assertEquals(5, $content['safetyStock']);
        $this->assertTrue($content['dynamicRopEnabled']);
    }

    public function testCreateOrUpdateReturns400OnInvalidArgumentException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('Validation failed'));

        $this->repoMock->expects($this->never())->method('save');

        $response = $this->controller->createOrUpdate($this->requestMock, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Validation failed', $content['error']);
    }

    public function testCreateOrUpdateReturns500OnInternalError(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new Exception('Database connection failed'));

        $this->repoMock->expects($this->never())->method('save');

        $response = $this->controller->createOrUpdate($this->requestMock, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
