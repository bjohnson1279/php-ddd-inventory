<?php

namespace Tests\Unit\Domain\Inventory\Entities;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\ValueObjects\TransactionType;

class ProductLedgerTest extends TestCase
{
    private function createProduct(): Product
    {
        return Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );
    }

    public function testCreatingProductWithStockRecordsReceiptTransaction(): void
    {
        $product = $this->createProduct();
        $txns = $product->getPendingTransactions();

        $this->assertCount(1, $txns);
        $this->assertEquals(TransactionType::RECEIPT, $txns[0]->getType()->getValue());
        $this->assertEquals(10, $txns[0]->getQuantityChange());
        $this->assertEquals(Condition::NEW, $txns[0]->getCondition()->getValue());
    }

    public function testReceiveStockRecordsReceiptTransaction(): void
    {
        $product = $this->createProduct();
        $product->clearPendingTransactions();

        $product->receiveStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(5), 'PO-001');
        $txns = $product->getPendingTransactions();

        $this->assertCount(1, $txns);
        $this->assertEquals(TransactionType::RECEIPT, $txns[0]->getType()->getValue());
        $this->assertEquals(5, $txns[0]->getQuantityChange());
        $this->assertEquals('PO-001', $txns[0]->getReference());
    }

    public function testSaleRecordsNegativeTransaction(): void
    {
        $product = $this->createProduct();
        $product->clearPendingTransactions();

        $product->processSaleAt(new LocationId('LOC-STOREFRONT'), new Quantity(3), 'ORDER-42');
        $txns = $product->getPendingTransactions();

        $this->assertCount(1, $txns);
        $this->assertEquals(TransactionType::SALE, $txns[0]->getType()->getValue());
        $this->assertEquals(-3, $txns[0]->getQuantityChange());
        $this->assertEquals('ORDER-42', $txns[0]->getReference());
    }

    public function testReturnRecordsPositiveTransaction(): void
    {
        $product = $this->createProduct();
        $product->clearPendingTransactions();

        $product->processReturnAt(new LocationId('LOC-STOREFRONT'), new Quantity(1), new Condition(Condition::DAMAGED));
        $txns = $product->getPendingTransactions();

        $this->assertCount(1, $txns);
        $this->assertEquals(TransactionType::RETURN, $txns[0]->getType()->getValue());
        $this->assertEquals(1, $txns[0]->getQuantityChange());
        $this->assertEquals(Condition::DAMAGED, $txns[0]->getCondition()->getValue());
    }

    public function testTransferRecordsTwoTransactions(): void
    {
        $product = $this->createProduct();
        $product->clearPendingTransactions();

        $product->transferStock(
            new LocationId('LOC-STOREFRONT'),
            new LocationId('LOC-BACKROOM'),
            new Quantity(4)
        );

        $txns = $product->getPendingTransactions();
        $this->assertCount(2, $txns);

        $types = array_map(fn($t) => $t->getType()->getValue(), $txns);
        $this->assertContains(TransactionType::DISPATCH, $types);
        $this->assertContains(TransactionType::RECEIPT, $types);
    }

    public function testClearPendingTransactionsEmptiesLedger(): void
    {
        $product = $this->createProduct();
        $this->assertNotEmpty($product->getPendingTransactions());

        $product->clearPendingTransactions();
        $this->assertEmpty($product->getPendingTransactions());
    }

    public function testReconcileStockAtRecordsAdjustment(): void
    {
        $product = $this->createProduct();
        $product->clearPendingTransactions();

        // Physical count shows 8, system has 10 → difference of -2
        $product->reconcileStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(8), 'COUNT-001');
        $txns = $product->getPendingTransactions();

        $this->assertCount(1, $txns);
        $this->assertEquals(TransactionType::ADJUSTMENT, $txns[0]->getType()->getValue());
        $this->assertEquals(-2, $txns[0]->getQuantityChange());
    }

    public function testReconcileStockWithNoDifferenceRecordsNoTransaction(): void
    {
        $product = $this->createProduct();
        $product->clearPendingTransactions();

        // Physical count matches system — no transaction should be recorded
        $product->reconcileStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(10), 'COUNT-002');

        $this->assertEmpty($product->getPendingTransactions());
    }
}
