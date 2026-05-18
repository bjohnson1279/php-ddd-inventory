<?php

namespace Tests\Unit\Domain\Inventory\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Services\OpeningBalanceService;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryLedgerRepository;
use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;

class OpeningBalanceServiceTest extends TestCase
{
    public function testProcessPostsEntries()
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_ob_' . uniqid();
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        $service = new OpeningBalanceService($ledger, $events);

        $on = new StockOnboarding(bin2hex(random_bytes(4)), 'tenant-1', 'loc-1', new \DateTimeImmutable('2025-01-01'));
        $on->setItem('variant-x', 5, 1500);
        $on->setItem('variant-y', 2, 500);
        $on->submit();

        $service->process($on, 'actor-1');

        $this->assertEquals(5, $ledger->currentQuantity('variant-x'));
        $this->assertEquals(2, $ledger->currentQuantity('variant-y'));
    }

    public function testProcessConflictThrows()
    {
        $this->expectException(\DomainException::class);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_ob_' . uniqid();
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        // pre-seed an entry for variant-z at same location
        $ledger->append(new \InventoryApp\Domain\Inventory\Entities\LedgerEntry(bin2hex(random_bytes(4)), 'variant-z', 1, \InventoryApp\Domain\Inventory\Enums\ReasonCode::PurchaseReceipt, 'actor', null, new \DateTimeImmutable(), ['locationId' => 'loc-9']));

        $service = new OpeningBalanceService($ledger, $events);

        $on = new StockOnboarding(bin2hex(random_bytes(4)), 'tenant-1', 'loc-9', new \DateTimeImmutable('2025-01-01'));
        $on->setItem('variant-z', 10, 2000);
        $on->submit();

        $service->process($on, 'actor-1');
    }
}
