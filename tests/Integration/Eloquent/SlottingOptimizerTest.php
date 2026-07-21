<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Domain\Inventory\Services\SlottingOptimizer;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class SlottingOptimizerTest extends TestCase
{
    private string $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = 'tenant-' . bin2hex(random_bytes(4));

        Capsule::table('ledger_entries')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('products')->delete();
        Capsule::table('warehouse_locations')->delete();
        Capsule::table('locations')->delete();
        Capsule::table('locations')->whereNotIn('id', ['LOC-INT', 'LOC-INT-quarantine'])->delete();

        // Seed tenant to satisfy foreign key constraint
        Capsule::table('tenants')->insert([
            'id' => $this->tenantId,
            'name' => 'Test Tenant ' . $this->tenantId
        ]);
    }

    public function test_slotting_optimization_computes_travel_savings(): void
    {
        // Seed base locations to satisfy foreign key constraint
        Capsule::table('locations')->insert([
            [
                'id' => 'LOC-CLOSE',
                'name' => 'Close Location',
                'type' => 'bin'
            ],
            [
                'id' => 'LOC-FAR',
                'name' => 'Far Location',
                'type' => 'bin'
            ]
        ]);

        // 1. Seed two locations
        Capsule::table('warehouse_locations')->insert([
                'id' => 'LOC-CLOSE',

            [
                'warehouse_id' => 'WH1',
                'zone' => 'Z1',
                'aisle' => 'A1',
                'rack' => 'R1',
                'shelf' => 'S1',
                'bin' => 'B1',
                'max_weight_grams' => 1000,
                'max_volume_cubic_meters' => 1.0,
                'grid_x' => 1,
                'grid_y' => 1,
            ],
            [
                'id' => 'LOC-FAR',
                'warehouse_id' => 'WH1',
                'zone' => 'Z1',
                'aisle' => 'A2',
                'rack' => 'R1',
                'shelf' => 'S1',
                'bin' => 'B1',
                'max_weight_grams' => 1000,
                'max_volume_cubic_meters' => 1.0,
                'grid_x' => 10,
                'grid_y' => 10,
            ]
        ]);

        // 2. Seed products
        $prodId1 = uuidv4();
        $prodId2 = uuidv4();

        Capsule::table('products')->insert([
            [
                'id' => $prodId1,
                'tenant_id' => $this->tenantId,
                'sku' => 'SKU-HIGH',
                'name' => 'High Velocity Product',
                'department' => 'GEN',
                'reorder_threshold' => 10,
                'version_id' => 1
            ],
            [
                'id' => $prodId2,
                'tenant_id' => $this->tenantId,
                'sku' => 'SKU-LOW',
                'name' => 'Low Velocity Product',
                'department' => 'GEN',
                'reorder_threshold' => 10,
                'version_id' => 1
            ]
        ]);

        // 3. Map inventory locations
        // SKU-HIGH is currently far away, SKU-LOW is close
        Capsule::table('product_locations')->insert([
            [
                'product_id' => $prodId1,
                'location_id' => 'LOC-FAR',
                'stock_quantity' => 100,
            ],
            [
                'product_id' => $prodId2,
                'location_id' => 'LOC-CLOSE',
                'stock_quantity' => 50,
            ]
        ]);

        // 4. Seed dispatches (ledger sales)
        $nowStr = date('Y-m-d H:i:s');
        Capsule::table('ledger_entries')->insert([
            [
                'id' => uuidv4(),
                'tenant_id' => $this->tenantId,
                'variant_id' => 'SKU-HIGH',
                'quantity' => -80, // sale dispatch
                'reason' => 'sale',
                'actor_id' => 'system',
                'reference_id' => 'sale-1',
                'occurred_at' => $nowStr,
                'metadata' => json_encode(['locationId' => 'LOC-FAR']),
                'created_at' => $nowStr,
            ],
            [
                'id' => uuidv4(),
                'tenant_id' => $this->tenantId,
                'variant_id' => 'SKU-LOW',
                'quantity' => -2,
                'reason' => 'sale',
                'actor_id' => 'system',
                'reference_id' => 'sale-2',
                'occurred_at' => $nowStr,
                'metadata' => json_encode(['locationId' => 'LOC-CLOSE']),
                'created_at' => $nowStr,
            ]
        ]);

        $optimizer = new SlottingOptimizer();
        $suggestions = $optimizer->generateSuggestions();

        $this->assertCount(1, $suggestions);
        $sugg = $suggestions[0];

        $this->assertEquals('SKU-HIGH', $sugg['sku']);
        $this->assertEquals('LOC-FAR', $sugg['currentLocationId']);
        $this->assertEquals(20, $sugg['currentDistance']);
        $this->assertEquals(80, $sugg['currentVelocity']);
        $this->assertEquals('LOC-CLOSE', $sugg['recommendedLocationId']);
        $this->assertEquals(2, $sugg['recommendedDistance']);
        $this->assertEquals('SKU-LOW', $sugg['potentialSwapSku']);

        // savings = velocity (80) * distDiff (18) * 2 = 2880
        $this->assertEquals(2880, $sugg['estimatedSavings']);
    }
}
