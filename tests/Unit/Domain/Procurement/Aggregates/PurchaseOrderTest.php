<?php

namespace Tests\Unit\Domain\Procurement\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use DomainException;
use Ramsey\Uuid\Uuid;

class PurchaseOrderTest extends TestCase
{
    private function createPurchaseOrder(PurchaseOrderStatus $status = PurchaseOrderStatus::Draft): PurchaseOrder
    {
        return new PurchaseOrder(
            id: 'po-1',
            purchaseOrderNumber: 'PO-001',
            vendorId: 'vendor-1',
            tenantId: 'tenant-1',
            locationId: 'loc-1',
            status: $status
        );
    }

    private function createItem(string $variantId, int $quantity): PurchaseOrderItem
    {
        return new PurchaseOrderItem(
            id: Uuid::uuid4()->toString(),
            variantId: $variantId,
            quantity: $quantity,
            unitCostCents: 1000
        );
    }

    public function test_it_initializes_with_default_draft_status(): void
    {
        $po = new PurchaseOrder(
            id: 'po-1',
            purchaseOrderNumber: 'PO-001',
            vendorId: 'vendor-1',
            tenantId: 'tenant-1',
            locationId: 'loc-1'
        );

        $this->assertEquals(PurchaseOrderStatus::Draft, $po->getStatus());
        $this->assertEmpty($po->getItems());
    }

    public function test_it_can_add_an_item(): void
    {
        $po = $this->createPurchaseOrder();
        $item = $this->createItem('var-1', 10);

        $po->addItem($item);

        $this->assertCount(1, $po->getItems());
        $this->assertSame($item, $po->getItems()[0]);
    }

    public function test_it_can_be_approved(): void
    {
        $po = $this->createPurchaseOrder();

        $po->approve();

        $this->assertEquals(PurchaseOrderStatus::Approved, $po->getStatus());
    }

    public function test_it_throws_when_approving_non_draft(): void
    {
        $po = $this->createPurchaseOrder(PurchaseOrderStatus::Approved);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Only draft purchase orders can be approved.");

        $po->approve();
    }

    public function test_it_can_be_sent(): void
    {
        $po = $this->createPurchaseOrder(PurchaseOrderStatus::Approved);

        $po->send();

        $this->assertEquals(PurchaseOrderStatus::Sent, $po->getStatus());
    }

    public function test_it_throws_when_sending_non_approved(): void
    {
        $po = $this->createPurchaseOrder(PurchaseOrderStatus::Draft);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Only approved purchase orders can be sent.");

        $po->send();
    }

    public function test_it_throws_when_receiving_items_on_invalid_status(): void
    {
        $po = $this->createPurchaseOrder(PurchaseOrderStatus::Draft);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Can only receive items on Sent or Partially Received purchase orders.");

        $po->receiveItems('var-1', 5);
    }

    public function test_it_throws_when_receiving_items_for_unknown_variant(): void
    {
        $po = $this->createPurchaseOrder(PurchaseOrderStatus::Sent);
        $po->addItem($this->createItem('var-1', 10));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Item with variant ID unknown-var not found in this purchase order.");

        $po->receiveItems('unknown-var', 5);
    }

    public function test_it_can_partially_receive_items_and_updates_status(): void
    {
        $po = $this->createPurchaseOrder(PurchaseOrderStatus::Sent);
        $po->addItem($this->createItem('var-1', 10));
        $po->addItem($this->createItem('var-2', 5));

        // Receive full quantity of var-1, but var-2 is still pending
        $po->receiveItems('var-1', 10);

        $this->assertEquals(PurchaseOrderStatus::PartiallyReceived, $po->getStatus());
    }

    public function test_it_fully_receives_items_and_updates_status(): void
    {
        $po = $this->createPurchaseOrder(PurchaseOrderStatus::Sent);
        $po->addItem($this->createItem('var-1', 10));

        $po->receiveItems('var-1', 10);

        $this->assertEquals(PurchaseOrderStatus::Received, $po->getStatus());
    }

    public function test_it_can_be_closed(): void
    {
        $po = $this->createPurchaseOrder();

        $po->close();

        $this->assertEquals(PurchaseOrderStatus::Closed, $po->getStatus());
    }
}
