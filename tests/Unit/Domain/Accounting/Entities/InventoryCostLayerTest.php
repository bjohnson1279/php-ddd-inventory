<?php

namespace Tests\Unit\Domain\Accounting\Entities;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Shared\ValueObjects\Money;

class InventoryCostLayerTest extends TestCase
{
    private function createLayer(int $originalQuantity = 10, int $unitCostCents = 100): InventoryCostLayer
    {
        return new InventoryCostLayer(
            id: 'layer-1',
            variantId: 'variant-1',
            tenantId: 'tenant-1',
            originalQuantity: $originalQuantity,
            unitCostCents: $unitCostCents,
            receivedAt: new \DateTimeImmutable('2023-01-01 10:00:00'),
            purchaseOrderId: 'po-1'
        );
    }

    public function testInitialization(): void
    {
        $layer = clone $this->createLayer(originalQuantity: 50, unitCostCents: 200);

        $this->assertEquals('layer-1', $layer->id);
        $this->assertEquals('variant-1', $layer->variantId);
        $this->assertEquals('tenant-1', $layer->tenantId);
        $this->assertEquals(50, $layer->originalQuantity);
        $this->assertEquals(200, $layer->unitCostCents);
        $this->assertEquals(50, $layer->remainingQuantity());
        $this->assertEquals('po-1', $layer->purchaseOrderId);
        $this->assertFalse($layer->isExhausted());
    }

    public function testConsumePartially(): void
    {
        $layer = clone $this->createLayer(originalQuantity: 10);

        $consumed = $layer->consume(4);

        $this->assertEquals(4, $consumed);
        $this->assertEquals(6, $layer->remainingQuantity());
        $this->assertFalse($layer->isExhausted());
    }

    public function testConsumeMoreThanRemaining(): void
    {
        $layer = clone $this->createLayer(originalQuantity: 5);

        $consumed = $layer->consume(10);

        $this->assertEquals(5, $consumed);
        $this->assertEquals(0, $layer->remainingQuantity());
        $this->assertTrue($layer->isExhausted());
    }

    public function testConsumeExactRemaining(): void
    {
        $layer = clone $this->createLayer(originalQuantity: 5);

        $consumed = $layer->consume(5);

        $this->assertEquals(5, $consumed);
        $this->assertEquals(0, $layer->remainingQuantity());
        $this->assertTrue($layer->isExhausted());
    }

    public function testConsumeWhenAlreadyExhausted(): void
    {
        $layer = clone $this->createLayer(originalQuantity: 0);

        $consumed = $layer->consume(5);

        $this->assertEquals(0, $consumed);
        $this->assertEquals(0, $layer->remainingQuantity());
        $this->assertTrue($layer->isExhausted());
    }

    public function testRemainingCostCents(): void
    {
        $layer = clone $this->createLayer(originalQuantity: 10, unitCostCents: 150);

        $this->assertEquals(1500, $layer->remainingCostCents());

        $layer->consume(2);

        $this->assertEquals(1200, $layer->remainingCostCents()); // 8 * 150
    }

    public function testSetRemainingQuantity(): void
    {
        $layer = clone $this->createLayer(originalQuantity: 10);

        $layer->setRemainingQuantity(20);

        $this->assertEquals(20, $layer->remainingQuantity());
    }

    public function testInitializationWithMoneyValueObject(): void
    {
        $money = Money::fromCents(250, 'USD');
        $layer = new InventoryCostLayer(
            id: 'layer-money',
            variantId: 'variant-1',
            tenantId: 'tenant-1',
            originalQuantity: 10,
            unitCostCents: $money,
            receivedAt: new \DateTimeImmutable('2023-01-01 10:00:00')
        );

        $this->assertSame(250, $layer->unitCostCents);
        $this->assertSame($money, $layer->unitCost);
        $this->assertTrue($layer->getUnitCost()->equals($money));
        $this->assertTrue($layer->getRemainingCost()->equals(Money::fromCents(2500, 'USD')));

        $layer->consume(4);
        $this->assertTrue($layer->getRemainingCost()->equals(Money::fromCents(1500, 'USD')));
    }

    public function testNegativeUnitCostThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit cost cannot be negative.');

        new InventoryCostLayer(
            id: 'layer-neg',
            variantId: 'variant-1',
            tenantId: 'tenant-1',
            originalQuantity: 10,
            unitCostCents: -50,
            receivedAt: new \DateTimeImmutable('2023-01-01 10:00:00')
        );
    }
}
