<?php

require_once __DIR__ . '/../bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Domain\Inventory\Services\DemandForecaster;
use InventoryApp\Domain\Shared\ValueObjects\SKU;
use InventoryApp\Domain\Shared\ValueObjects\LocationId;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Identity\NullEventDispatcher;

$tenantId = 'debug-tenant-' . bin2hex(random_bytes(4));
Capsule::table('tenants')->insertOrIgnore([['id' => $tenantId, 'name' => 'Debug Tenant']]);

$sku = 'IPHONE-15';
$locationId = 'LOC-INT';

Capsule::table('products')->insert([
    'id' => uuidv4(),
    'tenant_id' => $tenantId,
    'sku' => $sku,
    'name' => 'iPhone 15',
    'department' => 'Electronics',
    'reorder_threshold' => 10,
    'version_id' => 1
]);

$twoDaysAgo = date('Y-m-d H:i:s', time() - 2 * 24 * 3600);
$fiveDaysAgo = date('Y-m-d H:i:s', time() - 5 * 24 * 3600);
$tenDaysAgo = date('Y-m-d H:i:s', time() - 10 * 24 * 3600);

Capsule::table('ledger_entries')->insert([
    [
        'id' => uuidv4(),
        'tenant_id' => $tenantId,
        'variant_id' => $sku,
        'quantity' => -10,
        'reason' => 'sale',
        'actor_id' => 'system',
        'reference_id' => '1',
        'occurred_at' => $twoDaysAgo,
        'metadata' => json_encode(['locationId' => $locationId]),
        'created_at' => date('Y-m-d H:i:s'),
    ],
    [
        'id' => uuidv4(),
        'tenant_id' => $tenantId,
        'variant_id' => $sku,
        'quantity' => -10,
        'reason' => 'sale',
        'actor_id' => 'system',
        'reference_id' => '2',
        'occurred_at' => $fiveDaysAgo,
        'metadata' => json_encode(['locationId' => $locationId]),
        'created_at' => date('Y-m-d H:i:s'),
    ],
    [
        'id' => uuidv4(),
        'tenant_id' => $tenantId,
        'variant_id' => $sku,
        'quantity' => -10,
        'reason' => 'sale',
        'actor_id' => 'system',
        'reference_id' => '3',
        'occurred_at' => $tenDaysAgo,
        'metadata' => json_encode(['locationId' => $locationId]),
        'created_at' => date('Y-m-d H:i:s'),
    ]
]);

use InventoryApp\Infrastructure\ServiceContainer;

$forecaster = new DemandForecaster(
    ServiceContainer::productRepo($tenantId),
    ServiceContainer::ledgerRepo($tenantId),
    ServiceContainer::reorderPolicyRepo($tenantId),
    ServiceContainer::demandForecastRepo($tenantId)
);

// Now let's trace seasonalMultiplier calculation
$ledgerRepo = ServiceContainer::ledgerRepo($tenantId);
$now = new DateTimeImmutable();
$oneYearAgo = $now->modify('-365 days');
$entries = $ledgerRepo->entriesFor($sku, $locationId);

$dispatches = array_filter($entries, function ($e) use ($oneYearAgo) {
    return $e->occurredAt >= $oneYearAgo &&
        $e->quantity < 0 &&
        ($e->reason === \InventoryApp\Domain\Inventory\Enums\ReasonCode::Sale || $e->reason === \InventoryApp\Domain\Inventory\Enums\ReasonCode::KitSale);
});

echo "Number of dispatches found: " . count($dispatches) . "\n";
foreach ($dispatches as $d) {
    echo "  Dispatch: qty=" . $d->quantity . ", occurredAt=" . $d->occurredAt->format('Y-m-d H:i:s') . "\n";
}

$seasonalMultiplier = 1.0;
if (!empty($dispatches)) {
    $monthlySales = array_fill(0, 12, 0);
    foreach ($dispatches as $entry) {
        $month = (int) $entry->occurredAt->format('n') - 1;
        $monthlySales[$month] += abs($entry->quantity);
    }

    $totalSales = array_sum($monthlySales);
    $activeMonths = count(array_filter($monthlySales, fn($s) => $s > 0)) ?: 1;
    $overallMonthlyAverage = $totalSales / $activeMonths;

    echo "Monthly sales profile: " . json_encode($monthlySales) . "\n";
    echo "Total sales: $totalSales, activeMonths: $activeMonths, overallMonthlyAverage: $overallMonthlyAverage\n";

    if ($overallMonthlyAverage > 0) {
        $targetMonth = (int) (new \DateTime())->format('n') - 1;
        $targetMonthSales = $monthlySales[$targetMonth];
        echo "Target month index: $targetMonth, targetMonthSales: $targetMonthSales\n";
        if ($targetMonthSales > 0) {
            $seasonalMultiplier = $targetMonthSales / $overallMonthlyAverage;
            echo "Calculated raw seasonalMultiplier: $seasonalMultiplier\n";
            $seasonalMultiplier = max(0.3, min(3.0, $seasonalMultiplier));
        }
    }
}

echo "Final seasonalMultiplier: $seasonalMultiplier\n";
$forecast = $forecaster->generateDemandForecast(new SKU($sku), new LocationId($locationId), 15, 1.2);
echo "Forecasted confidenceLevel: " . $forecast->confidenceLevel . "\n";
