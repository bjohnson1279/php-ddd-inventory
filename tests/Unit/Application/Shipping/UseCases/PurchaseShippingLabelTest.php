<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;

use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Application\Ports\LabelResult;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Shipping\Events\ShipmentCreatedEvent;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;

class PurchaseShippingLabelTest extends TestCase
{
    public function test_it_successfully_purchases_shipping_label_and_records_all_domain_events(): void
    {
        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $carrierService = $this->createMock(CarrierServiceInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $ledgerRepository = $this->createMock(LedgerRepositoryInterface::class);
        $journalRepository = $this->createMock(JournalRepositoryInterface::class);
        $outboxRepository = $this->createMock(OutboxRepositoryInterface::class);

        $skuStr = 'TEST-SKU';
        $sku = new SKU($skuStr);
        $locationIdStr = 'LOC-WAREHOUSE-1';
        $locationId = new LocationId($locationIdStr);
        $quantityInt = 5;
        $quantity = new Quantity($quantityInt);
        $destinationAddress = '123 Test St, Test City, TS 12345';
        $carrier = 'UPS';
        $tenantId = 'TENANT-123';

        $product = Product::create(
            'prod-123',
            $sku,
            'Test Product',
            new Department('TEST'),
            $locationId,
            new Quantity(10)
        );

        $productRepository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn($product);

        $productRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) use ($quantityInt) {
                return $p->getTotalStockQuantity()->getValue() === (10 - $quantityInt);
            }));

        $labelResult = new LabelResult('TRACK-987654321', 'https://example.com/label.png', 1500);
        $carrierService->expects($this->once())
            ->method('generateLabel')
            ->with($skuStr, $quantityInt, $destinationAddress, $carrier)
            ->willReturn($labelResult);

        $ledgerRepository->expects($this->once())
            ->method('append')
            ->with($this->callback(function ($entry) use ($skuStr, $quantityInt, $locationIdStr) {
                return $entry->variantId === $skuStr
                    && $entry->quantity === -$quantityInt
                    && $entry->metadata['locationId'] === $locationIdStr;
            }));

        $shipmentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Shipment $shipment) use ($skuStr, $quantityInt, $destinationAddress, $carrier, $labelResult) {
                return $shipment->sku === $skuStr
                    && $shipment->quantity === $quantityInt
                    && $shipment->destinationAddress === $destinationAddress
                    && $shipment->carrier === $carrier
                    && $shipment->trackingNumber === $labelResult->trackingNumber
                    && $shipment->labelUrl === $labelResult->labelUrl
                    && $shipment->shippingRateCents === $labelResult->rateCents;
            }));

        $journalRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (JournalEntry $entry) use ($tenantId, $labelResult) {
                return $entry->tenantId === $tenantId
                    && str_contains($entry->description, $labelResult->trackingNumber)
                    && count($entry->lines()) === 2;
            }));

        $outboxRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ShipmentCreatedEvent $event) use ($skuStr, $quantityInt, $carrier, $labelResult) {
                return $event->sku === $skuStr
                    && $event->quantity === $quantityInt
                    && $event->carrier === $carrier
                    && $event->trackingNumber === $labelResult->trackingNumber
                    && $event->rateCents === $labelResult->rateCents;
            }));

        $useCase = new PurchaseShippingLabel(
            $shipmentRepository,
            $carrierService,
            $productRepository,
            $ledgerRepository,
            $journalRepository,
            $outboxRepository
        );

        $result = $useCase->execute(
            $skuStr,
            $quantityInt,
            $destinationAddress,
            $carrier,
            $locationIdStr,
            $tenantId
        );

        $this->assertInstanceOf(PurchaseShippingLabelResult::class, $result);
        $this->assertEquals($labelResult->trackingNumber, $result->trackingNumber);
        $this->assertEquals($labelResult->labelUrl, $result->labelUrl);
        $this->assertEquals($labelResult->rateCents, $result->rateCents);
    }

    public function invalidParametersProvider(): array
    {
        return [
            'empty sku' => ['', 5, '123 St', 'UPS', 'LOC-1', 'TENANT-1'],
            'zero quantity' => ['SKU', 0, '123 St', 'UPS', 'LOC-1', 'TENANT-1'],
            'negative quantity' => ['SKU', -5, '123 St', 'UPS', 'LOC-1', 'TENANT-1'],
            'empty destination' => ['SKU', 5, '', 'UPS', 'LOC-1', 'TENANT-1'],
            'empty carrier' => ['SKU', 5, '123 St', '', 'LOC-1', 'TENANT-1'],
            'empty location' => ['SKU', 5, '123 St', 'UPS', '', 'TENANT-1'],
            'empty tenant' => ['SKU', 5, '123 St', 'UPS', 'LOC-1', ''],
        ];
    }

    /**
     * @dataProvider invalidParametersProvider
     */
    public function test_it_throws_exception_if_required_parameters_are_missing(
        string $sku,
        int $quantity,
        string $destinationAddress,
        string $carrier,
        string $locationId,
        string $tenantId
    ): void {
        $useCase = new PurchaseShippingLabel(
            $this->createMock(ShipmentRepositoryInterface::class),
            $this->createMock(CarrierServiceInterface::class),
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(LedgerRepositoryInterface::class),
            $this->createMock(JournalRepositoryInterface::class),
            $this->createMock(OutboxRepositoryInterface::class)
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required parameters for shipping label purchase.');

        $useCase->execute($sku, $quantity, $destinationAddress, $carrier, $locationId, $tenantId);
    }

    public function test_it_throws_exception_if_product_not_found(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $skuStr = 'UNKNOWN-SKU';
        $locationId = 'LOC-1';

        $productRepository->expects($this->once())
            ->method('findBySku')
            ->with(new SKU($skuStr))
            ->willReturn(null);

        $useCase = new PurchaseShippingLabel(
            $this->createMock(ShipmentRepositoryInterface::class),
            $this->createMock(CarrierServiceInterface::class),
            $productRepository,
            $this->createMock(LedgerRepositoryInterface::class),
            $this->createMock(JournalRepositoryInterface::class),
            $this->createMock(OutboxRepositoryInterface::class)
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Inventory item not found for SKU {$skuStr} at location {$locationId}.");

        $useCase->execute($skuStr, 5, '123 St', 'UPS', $locationId, 'TENANT-1');
    }

    public function test_it_throws_insufficient_stock_exception_if_quantity_exceeds_available(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $skuStr = 'TEST-SKU';
        $sku = new SKU($skuStr);
        $locationIdStr = 'LOC-1';
        $locationId = new LocationId($locationIdStr);

        $product = Product::create(
            'prod-123',
            $sku,
            'Test Product',
            new Department('TEST'),
            $locationId,
            new Quantity(3)
        );

        $productRepository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn($product);

        $useCase = new PurchaseShippingLabel(
            $this->createMock(ShipmentRepositoryInterface::class),
            $this->createMock(CarrierServiceInterface::class),
            $productRepository,
            $this->createMock(LedgerRepositoryInterface::class),
            $this->createMock(JournalRepositoryInterface::class),
            $this->createMock(OutboxRepositoryInterface::class)
        );

        $this->expectException(InsufficientStockException::class);

        $useCase->execute($skuStr, 5, '123 St', 'UPS', $locationIdStr, 'TENANT-1');
    }
}
