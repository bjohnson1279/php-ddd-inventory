<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Exception;

class ShippingControllerTest extends TestCase
{
    private ShippingController $controller;
    private $requestMock;
    private $shipmentRepoMock;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->shipmentRepoMock = $this->createMock(ShipmentRepositoryInterface::class);
    }

    public function testGetShipmentsReturns200WithFormattedShipments(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2025-01-02 12:00:00');

        $shipment1 = new Shipment(
            id: 'SHIP-001',
            sku: 'SKU-ABC',
            quantity: 3,
            destinationAddress: '123 Main St, Springfield, OR',
            carrier: 'USPS',
            trackingNumber: 'TRACK-111',
            labelUrl: 'https://shipping.example.com/labels/111.pdf',
            shippingRateCents: 1250,
            status: ShipmentStatus::LabelGenerated,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $shipment2 = new Shipment(
            id: 'SHIP-002',
            sku: 'SKU-XYZ',
            quantity: 1,
            destinationAddress: '456 Elm St, Metropolis, NY',
            carrier: 'FedEx',
            trackingNumber: null,
            labelUrl: null,
            shippingRateCents: 850,
            status: ShipmentStatus::InTransit,
            createdAt: $createdAt,
            updatedAt: null
        );

        $this->shipmentRepoMock->expects($this->once())
            ->method('findAll')
            ->willReturn([$shipment1, $shipment2]);

        $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);

        $this->assertEquals([
            'id' => 'SHIP-001',
            'sku' => 'SKU-ABC',
            'quantity' => 3,
            'destinationAddress' => '123 Main St, Springfield, OR',
            'carrier' => 'USPS',
            'trackingNumber' => 'TRACK-111',
            'labelUrl' => 'https://shipping.example.com/labels/111.pdf',
            'shippingRateCents' => 1250,
            'status' => ShipmentStatus::LabelGenerated->value,
            'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $updatedAt->format(\DateTimeInterface::ATOM),
        ], $decoded[0]);

        $this->assertEquals([
            'id' => 'SHIP-002',
            'sku' => 'SKU-XYZ',
            'quantity' => 1,
            'destinationAddress' => '456 Elm St, Metropolis, NY',
            'carrier' => 'FedEx',
            'trackingNumber' => null,
            'labelUrl' => null,
            'shippingRateCents' => 850,
            'status' => ShipmentStatus::InTransit->value,
            'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $createdAt->format(\DateTimeInterface::ATOM),
        ], $decoded[1]);
    }

    public function testGetShipmentsReturns200WithEmptyListWhenNoShipments(): void
    {
        $this->shipmentRepoMock->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame([], $decoded);
    }

    public function testGetShipmentsReturns400OnDomainException(): void
    {
        $this->shipmentRepoMock->expects($this->once())
            ->method('findAll')
            ->willThrowException(new DomainException('Domain constraint violated'));

        $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Domain constraint violated'], $decoded);
    }

    public function testGetShipmentsReturns400OnInvalidArgumentException(): void
    {
        $this->shipmentRepoMock->expects($this->once())
            ->method('findAll')
            ->willThrowException(new InvalidArgumentException('Invalid argument'));

        $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Invalid argument'], $decoded);
    }

    public function testGetShipmentsReturns500OnGenericException(): void
    {
        $this->shipmentRepoMock->expects($this->once())
            ->method('findAll')
            ->willThrowException(new Exception('Database connection failed'));

        $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'An internal server error occurred.'], $decoded);
    }
}
