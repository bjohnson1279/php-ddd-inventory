<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Entities\Product;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentProductRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        // bootstrap already runs
    }

    public function test_save_and_find_by_sku(): void
    {
        $repo = new EloquentProductRepository('test-tenant');

        $id = uuidv4();
        $sku = new SKU('INTSKU1');
        $product = Product::create($id, $sku, 'Integration Product', new Department('GEN'), new LocationId('LOC-INT'), new Quantity(5));

        $repo->save($product);

        $found = $repo->findBySku($sku);
        $this->assertNotNull($found);
        $this->assertEquals($id, $found->getId());
        $this->assertEquals('Integration Product', $found->getName());

        // Verify inventory transaction (initial stock) was recorded
        $tx = \Illuminate\Database\Capsule\Manager::connection()->table('inventory_transactions')->where('product_id', $id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(5, $tx->quantity_change);
        $this->assertEquals('test-tenant', $tx->tenant_id);
    }
}
