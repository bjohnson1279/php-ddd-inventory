<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\DisassembleKit;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentKitRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentCostLayerRepository;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Accounting\Services\CostLayerService;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class DisassembleKitBenchmarkTest extends TestCase
{
    private DisassembleKit $useCase;
    private EloquentProductRepository $productRepo;
    private EloquentKitRepository $kitRepo;
    private EloquentCostLayerRepository $costLayerRepo;
    private EloquentLedgerRepository $ledgerRepo;

    protected function setUp(): void
    {
        Capsule::table('kits')->delete();
        Capsule::table('kit_components')->delete();
        Capsule::table('products')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('inventory_cost_layers')->delete();
        Capsule::table('ledger_entries')->delete();

        Capsule::table('tenants')->insertOrIgnore([
            ['id' => 'test-tenant', 'name' => 'Test Tenant 1']
        ]);

        Capsule::table('locations')->insertOrIgnore([
            ['id' => 'LOC-1', 'name' => 'Test Location 1', 'type' => 'WAREHOUSE']
        ]);

        $tenantId = 'test-tenant';
        $this->productRepo = new EloquentProductRepository($tenantId);
        $this->kitRepo = new EloquentKitRepository($tenantId);
        $this->costLayerRepo = new EloquentCostLayerRepository($tenantId);
        $this->ledgerRepo = new EloquentLedgerRepository($tenantId);
        $journalRepoMock = $this->createMock(JournalRepositoryInterface::class);
        $costLayerService = new CostLayerService($this->costLayerRepo);
        $journalService = new AccountingJournalService($journalRepoMock, $costLayerService);

        $this->useCase = new DisassembleKit(
            $this->kitRepo,
            $this->productRepo,
            $this->ledgerRepo,
            $this->costLayerRepo,
            $journalService
        );
    }

    public function testDisassembleKitPerformance(): void
    {
        $tenantId = 'test-tenant';
        $kitSku = 'BENCH-KIT-1';

        // 1. Create Kit with 5 components
        $kit = new Kit(Uuid::uuid4()->toString(), $kitSku, 'Benchmark Kit');
        for ($i = 1; $i <= 5; $i++) {
            $kit->addComponent("comp-var-{$i}", 2);
        }
        $this->kitRepo->save($kit);

        // 2. Create Product for Kit
        $kitProduct = Product::create(
            Uuid::uuid4()->toString(),
            new SKU($kitSku),
            'Benchmark Kit Product',
            new Department('KITS'),
            new LocationId('LOC-1'),
            new Quantity(100)
        );
        $this->productRepo->save($kitProduct);

        // Add ledger entry for Kit stock
        $this->ledgerRepo->append(new LedgerEntry(
            id: Uuid::uuid4()->toString(),
            variantId: $kitProduct->getId(),
            quantity: 100,
            reason: ReasonCode::OpeningBalance,
            actorId: 'system',
            referenceId: 'init-ref',
            occurredAt: new DateTimeImmutable()
        ));

        // 3. Create Kit Cost Layer
        $kitLayer = new InventoryCostLayer(
            Uuid::uuid4()->toString(),
            $kitProduct->getId(),
            $tenantId,
            100,
            5000,
            new DateTimeImmutable()
        );
        $this->costLayerRepo->save($kitLayer);

        // 4. Create Products and Cost Layers for each component
        for ($i = 1; $i <= 5; $i++) {
            $varId = "comp-var-{$i}";
            $compProduct = Product::create(
                $varId,
                new SKU("COMP-SKU-{$i}"),
                "Component {$i}",
                new Department('PARTS'),
                new LocationId('LOC-1'),
                new Quantity(50)
            );
            $this->productRepo->save($compProduct);

            $layer1 = new InventoryCostLayer(
                Uuid::uuid4()->toString(),
                $varId,
                $tenantId,
                50,
                1000 + ($i * 100),
                new DateTimeImmutable('-2 days')
            );
            $layer2 = new InventoryCostLayer(
                Uuid::uuid4()->toString(),
                $varId,
                $tenantId,
                50,
                1200 + ($i * 100),
                new DateTimeImmutable('-1 day')
            );
            $this->costLayerRepo->save($layer1);
            $this->costLayerRepo->save($layer2);
        }

        // Measure DisassembleKit execution
        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $start = microtime(true);
        $this->useCase->execute([
            'tenantId' => $tenantId,
            'locationId' => 'LOC-1',
            'kitSku' => $kitSku,
            'quantity' => 2,
            'actorId' => 'actor-bench',
            'referenceId' => 'ref-bench-1'
        ]);
        $end = microtime(true);

        $queries = Capsule::connection()->getQueryLog();
        $timeSpent = ($end - $start) * 1000;

        echo "\nDisassembleKit execution time: " . sprintf("%.2f ms", $timeSpent) . "\n";
        echo "Total DB queries executed: " . count($queries) . "\n";

        $this->assertNotEmpty($queries);
    }
}
