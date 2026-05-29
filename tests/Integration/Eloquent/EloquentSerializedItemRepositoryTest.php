<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentSerializedItemRepository;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentSerializedItemRepositoryTest extends TestCase
{
    private EloquentSerializedItemRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new EloquentSerializedItemRepository();
    }

    public function test_save_and_retrieve_serialized_item(): void
    {
        $tenantId = 'test-tenant';
        $variantId = uuidv4();
        $serial = new SerialNumber('SN-999-XYZ');
        $id = uuidv4();

        // 1. Create a serialized item in Pending status
        $item = new SerializedItem($id, $variantId, $serial, $tenantId, 'LOC-INT', SerializedItemStatus::Pending);

        // 2. Perform transition to InStock
        $item->receive('LOC-INT', 'user-1', 'PO-100');

        $this->repo->save($item);

        // 3. Verify registration check
        $this->assertTrue($this->repo->isRegistered($serial, $tenantId));
        $this->assertFalse($this->repo->isRegistered(new SerialNumber('SN-NOT-EXIST'), $tenantId));

        // 4. Find by serial
        $found = $this->repo->findBySerialOrFail($serial, $tenantId);
        $this->assertEquals($id, $found->id);
        $this->assertEquals($variantId, $found->variantId);
        $this->assertEquals('SN-999-XYZ', $found->serialNumber->value);
        $this->assertEquals(SerializedItemStatus::InStock, $found->status());
        $this->assertEquals('LOC-INT', $found->locationId());
        
        $history = $found->history();
        $this->assertCount(1, $history);
        $this->assertEquals(SerializedItemStatus::Pending, $history[0]->from);
        $this->assertEquals(SerializedItemStatus::InStock, $history[0]->to);
        $this->assertEquals('Received against PO PO-100', $history[0]->reason);
        $this->assertEquals('user-1', $history[0]->actorId);
        $this->assertEquals('PO-100', $history[0]->referenceId);

        // 5. Find by ID
        $foundById = $this->repo->findById($id);
        $this->assertNotNull($foundById);
        $this->assertEquals('SN-999-XYZ', $foundById->serialNumber->value);

        // 6. Find by variant and status count
        $itemsByVariant = $this->repo->findByVariant($variantId);
        $this->assertCount(1, $itemsByVariant);
        $this->assertEquals($id, $itemsByVariant[0]->id);

        $count = $this->repo->countByStatus($variantId, SerializedItemStatus::InStock);
        $this->assertEquals(1, $count);

        $countPending = $this->repo->countByStatus($variantId, SerializedItemStatus::Pending);
        $this->assertEquals(0, $countPending);
    }
}
