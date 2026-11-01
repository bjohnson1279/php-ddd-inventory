<?php

namespace Tests\Unit\Domain\Returns\Aggregates;

use InvalidArgumentException;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Entities\RMAItem;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use InventoryApp\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class RMATest extends TestCase
{
    private function createRMA(RMAStatus $status = RMAStatus::Requested, array $items = []): RMA
    {
        return new RMA(
            'rma-1',
            'RMA-100',
            new TenantId('tenant-1'),
            'cust-1',
            new LocationId('LOC-01-A'),
            $status,
            $items
        );
    }

    public function testInitializesCorrectly(): void
    {
        $item = new RMAItem('item-1', 'var-1', 2, 500);
        $rma = $this->createRMA(RMAStatus::Requested, [$item]);

        $this->assertEquals('rma-1', $rma->getId());
        $this->assertEquals('RMA-100', $rma->getRmaNumber());
        $this->assertEquals('tenant-1', $rma->getTenantId()->getValue());
        $this->assertEquals('cust-1', $rma->getCustomerId());
        $this->assertEquals('LOC-01-A', $rma->getLocationId()->getValue());
        $this->assertEquals(RMAStatus::Requested, $rma->getStatus());
        $this->assertCount(1, $rma->getItems());
        $this->assertNotNull($rma->getCreatedAt());
        $this->assertNotNull($rma->getUpdatedAt());
    }

    public function testCanAuthorizeRequestedRMA(): void
    {
        $rma = $this->createRMA(RMAStatus::Requested);
        $rma->authorize();

        $this->assertEquals(RMAStatus::Authorized, $rma->getStatus());
    }

    public function testCannotAuthorizeNonRequestedRMA(): void
    {
        $rma = $this->createRMA(RMAStatus::Authorized);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RMA must be in Requested status to be authorized.');
        $rma->authorize();
    }

    public function testCanRejectRequestedRMA(): void
    {
        $item = new RMAItem('item-1', 'var-1', 2, 500);
        $rma = $this->createRMA(RMAStatus::Requested, [$item]);
        $rma->reject();

        $this->assertEquals(RMAStatus::Rejected, $rma->getStatus());
        $this->assertTrue($item->isFullyProcessed());
    }

    public function testCannotRejectNonRequestedRMA(): void
    {
        $rma = $this->createRMA(RMAStatus::Authorized);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RMA must be in Requested status to be rejected.');
        $rma->reject();
    }

    public function testCannotReceiveItemWhenRMAIsNotAuthorizedOrReceived(): void
    {
        $item = new RMAItem('item-1', 'var-1', 2, 500);
        $rma = $this->createRMA(RMAStatus::Requested, [$item]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RMA must be in Authorized or Received status to receive items.');
        $rma->receiveItem('var-1', 1, RMADisposition::Restock);
    }

    public function testThrowsWhenReceivingUnknownVariant(): void
    {
        $item = new RMAItem('item-1', 'var-1', 2, 500);
        $rma = $this->createRMA(RMAStatus::Authorized, [$item]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item with variant ID unknown-var not found in RMA.');
        $rma->receiveItem('unknown-var', 1, RMADisposition::Restock);
    }

    public function testPartialReceiptSetsStatusToReceived(): void
    {
        $item = new RMAItem('item-1', 'var-1', 5, 500);
        $rma = $this->createRMA(RMAStatus::Authorized, [$item]);

        $rma->receiveItem('var-1', 2, RMADisposition::Restock);
        $this->assertEquals(RMAStatus::Received, $rma->getStatus());
    }

    public function testFullReceiptSetsStatusToCompleted(): void
    {
        $item = new RMAItem('item-1', 'var-1', 5, 500);
        $rma = $this->createRMA(RMAStatus::Authorized, [$item]);

        $rma->receiveItem('var-1', 5, RMADisposition::Restock);
        $this->assertEquals(RMAStatus::Completed, $rma->getStatus());
    }

    public function testCalculatesTotalCostCorrectlyAcrossItems(): void
    {
        $item1 = new RMAItem('item-1', 'var-1', 4, 1500); // 4 * 1500 = 6000 cents ($60.00)
        $item2 = new RMAItem('item-2', 'var-2', 2, Money::fromCents(2500, 'USD')); // 2 * 2500 = 5000 cents ($50.00)

        $rma = $this->createRMA(RMAStatus::Requested, [$item1, $item2]);

        $totalCost = $rma->getTotalCost('USD');
        $this->assertEquals(11000, $totalCost->getAmountInCents());
        $this->assertEquals('USD', $totalCost->getCurrency());
        $this->assertEquals(110.0, $totalCost->getAmountInDollars());
    }
}
