<?php

namespace Tests\Unit\Domain\Inventory\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Services\InventoryService;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryLedgerRepository;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;

class InventoryServiceTest extends TestCase
{
    public function testDecrementForSaleReducesStock()
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_' . uniqid();
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        // seed with 10 units
        $ledger->append(new LedgerEntry(bin2hex(random_bytes(4)), 'variant-1', 10, ReasonCode::PurchaseReceipt, 'actor-1', null, new \DateTimeImmutable()));

        $svc = new InventoryService($ledger, $events);
        $svc->decrementForSale('variant-1', 3, 'sale-1', 'actor-1');

        $this->assertEquals(7, $ledger->currentQuantity('variant-1'));
    }

    public function testDecrementForSaleInsufficientThrows()
    {
        $this->expectException(\DomainException::class);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_' . uniqid();
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        // seed with 2 units
        $ledger->append(new LedgerEntry(bin2hex(random_bytes(4)), 'v2', 2, ReasonCode::PurchaseReceipt, 'actor-1', null, new \DateTimeImmutable()));

        $svc = new InventoryService($ledger, $events);
        $svc->decrementForSale('v2', 5, 'sale-2', 'actor-1');
    }

    public function testDecrementForKitSaleReducesAllComponents()
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_' . uniqid();
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        // seed two variants
        $ledger->append(new LedgerEntry(bin2hex(random_bytes(4)), 'a', 10, ReasonCode::PurchaseReceipt, 'actor', null, new \DateTimeImmutable()));
        $ledger->append(new LedgerEntry(bin2hex(random_bytes(4)), 'b', 6, ReasonCode::PurchaseReceipt, 'actor', null, new \DateTimeImmutable()));

        $svc = new InventoryService($ledger, $events);

        $kit = new class {
            public function components() {
                return [
                    (object)['variantId' => 'a', 'quantity' => 2],
                    (object)['variantId' => 'b', 'quantity' => 1],
                ];
            }
        };

        // sell 2 kits -> a: -4, b: -2
        $svc->decrementForKitSale($kit, 2, 'sale-kit-1', 'actor');

        $this->assertEquals(6, $ledger->currentQuantity('a'));
        $this->assertEquals(4, $ledger->currentQuantity('b'));
    }

    public function testDecrementForKitSaleInsufficientThrowsNoWrites()
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_' . uniqid();
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        // seed: variant a has 3, variant b has 1
        $ledger->append(new LedgerEntry(bin2hex(random_bytes(4)), 'a', 3, ReasonCode::PurchaseReceipt, 'actor', null, new \DateTimeImmutable()));
        $ledger->append(new LedgerEntry(bin2hex(random_bytes(4)), 'b', 1, ReasonCode::PurchaseReceipt, 'actor', null, new \DateTimeImmutable()));

        $svc = new InventoryService($ledger, $events);

        $kit = new class {
            public function components() {
                return [
                    (object)['variantId' => 'a', 'quantity' => 2],
                    (object)['variantId' => 'b', 'quantity' => 2],
                ];
            }
        };

        try {
            $svc->decrementForKitSale($kit, 1, 'sale-kit-2', 'actor');
            $this->fail('Expected exception due to insufficient stock');
        } catch (\DomainException $e) {
            // ensure no entries were appended (a remains 3, b remains 1)
            $this->assertEquals(3, $ledger->currentQuantity('a'));
            $this->assertEquals(1, $ledger->currentQuantity('b'));
        }
    }
}
