<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentInventoryCountRepository;
use InventoryApp\Domain\Inventory\Entities\InventoryCount;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentInventoryCountRepositoryTest extends TestCase
{
    public function test_save_and_find(): void
    {
        $repo = new EloquentInventoryCountRepository('test-tenant');
        $id = uuidv4();
        $count = InventoryCount::start($id);
        $count->recordCount(new SKU('INTSKU-A'), new LocationId('LOC-STOREFRONT'), new Quantity(7));

        $repo->save($count);

        $found = $repo->findById($id);
        $this->assertNotNull($found);
        $this->assertCount(1, $found->getItems());
        $items = $found->getItems();
        $this->assertEquals('INTSKU-A', $items[0]->getSku()->getValue());
        $this->assertEquals('LOC-STOREFRONT', $items[0]->getLocationId()->getValue());
        $this->assertEquals(7, $items[0]->getCountedQuantity()->getValue());
    }
}
