<?php

namespace Tests\Unit\Domain\Serial\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Serial\Services\SerializedInventoryService;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemorySerializedItemRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryLedgerRepository;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;

class SerializedInventoryServiceTest extends TestCase
{
    public function testRegisterAndReceiveAndSellAndConsistency()
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_si_' . uniqid();
        $serialRepo = new InMemorySerializedItemRepository($tmp);
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        $svc = new SerializedInventoryService($serialRepo, $ledger, $events);

        $serial = new SerialNumber('SN-12345');
        $item = $svc->register($serial, 'variant-100', 'tenant-1', 'loc-1', 'actor-1');
        $this->assertNotNull($item);

        // receive -> ledger +1
        $svc->receive($serial, 'tenant-1', 'loc-1', 'po-1', 1000, 'actor-1');
        $this->assertEquals(1, $ledger->currentQuantity('variant-100'));

        // sell -> ledger -1
        $svc->sell($serial, 'tenant-1', 'sale-1', 'actor-1');
        $this->assertEquals(0, $ledger->currentQuantity('variant-100'));
    }

    public function testRegisterDuplicateThrows()
    {
        $this->expectException(\DomainException::class);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpddd_si_' . uniqid();
        $serialRepo = new InMemorySerializedItemRepository($tmp);
        $ledger = new InMemoryLedgerRepository($tmp);
        $events = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);

        $svc = new SerializedInventoryService($serialRepo, $ledger, $events);

        $serial = new SerialNumber('SN-999');
        $svc->register($serial, 'v1', 'tenant-9', 'loc-1', 'actor');
        // second register should throw
        $svc->register($serial, 'v1', 'tenant-9', 'loc-1', 'actor');
    }
}
