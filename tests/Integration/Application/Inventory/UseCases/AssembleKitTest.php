<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\AssembleKit;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentKitRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentCostLayerRepository;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Accounting\Services\CostLayerService;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../../../bootstrap.php';

/** @group integration */
final class AssembleKitTest extends TestCase
{
    private AssembleKit $useCase;
    private EloquentProductRepository $productRepo;
    private EloquentKitRepository $kitRepo;
    private EloquentCostLayerRepository $costLayerRepo;
    private EloquentLedgerRepository $ledgerRepo;
    private JournalRepositoryInterface $journalRepoMock;

    protected function setUp(): void
    {
        Capsule::table('kits')->delete();
        Capsule::table('kit_components')->delete();
        Capsule::table('products')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('inventory_cost_layers')->delete();
        Capsule::table('ledger_entries')->delete();

                // Create the test tenant because postgres enforces foreign keys
        Capsule::table('tenants')->insertOrIgnore([
            ['id' => 'test-tenant', 'name' => 'Test Tenant 1']
        ]);

        // Create the test location because postgres enforces foreign keys
        Capsule::table('locations')->insertOrIgnore([
            ['id' => 'LOC-1', 'name' => 'Test Location 1', 'type' => 'WAREHOUSE']
        ]);

        $this->productRepo = new EloquentProductRepository('test-tenant');
        $this->kitRepo = new EloquentKitRepository();
        $this->costLayerRepo = new EloquentCostLayerRepository('test-tenant');
        $this->ledgerRepo = new EloquentLedgerRepository('test-tenant');

        $this->journalRepoMock = $this->createMock(JournalRepositoryInterface::class);
        $journalService = new AccountingJournalService($this->journalRepoMock, new CostLayerService($this->costLayerRepo));

        $this->useCase = new AssembleKit(
            $this->kitRepo,
            $this->productRepo,
            $this->ledgerRepo,
            $this->costLayerRepo,
            $journalService
        );
    }

    public function testAssembleKitSuccessfully()
    {
        // 1. Setup Component Products
        $compId1 = Uuid::uuid4()->toString();
        $compId2 = Uuid::uuid4()->toString();
        $compProduct1 = Product::create($compId1, new SKU('COMP-1'), 'Component 1', new Department('DEP'), new LocationId('LOC-1'), new Quantity(10));
        $compProduct2 = Product::create($compId2, new SKU('COMP-2'), 'Component 2', new Department('DEP'), new LocationId('LOC-1'), new Quantity(20));

        $this->productRepo->save($compProduct1);
        $this->productRepo->save($compProduct2);

        // 2. Setup Kit Product
        $kitId = Uuid::uuid4()->toString();
        $kitProduct = Product::create($kitId, new SKU('KIT-1'), 'Assembled Kit', new Department('DEP'), new LocationId('LOC-1'), new Quantity(0));
        $this->productRepo->save($kitProduct);

        // 3. Setup Cost Layers for Components
        $cl1 = new InventoryCostLayer(Uuid::uuid4()->toString(), $compId1, 'test-tenant', 10, 1500, new DateTimeImmutable());
        $cl2 = new InventoryCostLayer(Uuid::uuid4()->toString(), $compId2, 'test-tenant', 20, 2500, new DateTimeImmutable());
        $this->costLayerRepo->save($cl1);
        $this->costLayerRepo->save($cl2);

        // 4. Setup Kit Definition
        $kit = new Kit(Uuid::uuid4()->toString(), 'KIT-1', 'Kit Name');
        $kit->addComponent($compId1, 2); // needs 2 of COMP-1
        $kit->addComponent($compId2, 1); // needs 1 of COMP-2
        $this->kitRepo->save($kit);

        // Create initial ledger entries for component stock
        $this->ledgerRepo->append(new LedgerEntry(
            Uuid::uuid4()->toString(),
            $compId1,
            10,
            ReasonCode::OpeningBalance,
            'actor-1',
            'ref-1',
            new DateTimeImmutable(),
            ['locationId' => 'LOC-1']
        ));

        $this->ledgerRepo->append(new LedgerEntry(
            Uuid::uuid4()->toString(),
            $compId2,
            20,
            ReasonCode::OpeningBalance,
            'actor-1',
            'ref-1',
            new DateTimeImmutable(),
            ['locationId' => 'LOC-1']
        ));

        // Expect Journal Entry
        $this->journalRepoMock->expects($this->once())
             ->method('save')
             ->with($this->callback(function (JournalEntry $entry) {
                 return $entry->lines()[0]->amountCents === 5500; // (2 * 1500) + (1 * 2500) = 5500
             }));

        // 5. Execute Use Case
        $this->useCase->execute([
            'tenantId' => 'test-tenant',
            'locationId' => 'LOC-1',
            'kitSku' => 'KIT-1',
            'quantity' => 1,
            'actorId' => 'actor-1',
            'referenceId' => 'ref-1'
        ]);

        // 6. Assertions
        $updatedComp1 = $this->productRepo->findById($compId1);
        $updatedComp2 = $this->productRepo->findById($compId2);
        $updatedKit = $this->productRepo->findById($kitId);

        $this->assertEquals(8, $updatedComp1->getTotalStockQuantity()->getValue()); // 10 - 2
        $this->assertEquals(19, $updatedComp2->getTotalStockQuantity()->getValue()); // 20 - 1
        $this->assertEquals(1, $updatedKit->getTotalStockQuantity()->getValue());

        // Check new cost layer for the kit
        $kitLayers = $this->costLayerRepo->getActiveLayers($kitId);
        $this->assertCount(1, $kitLayers);
        $this->assertEquals(5500, $kitLayers[0]->unitCostCents); // 5500 / 1 unit
        $this->assertEquals(1, $kitLayers[0]->remainingQuantity());
    }
}
