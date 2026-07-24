<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Tenant\TenantConnectionManager;
use App\Domain\CRDT\CRDTStockResolver;
use App\Application\IoT\RFIDBulkScanIngestionService;
use App\Application\Autonomous\AutonomousInventoryEngine;

class Section6Test extends TestCase
{
    public function testTenantConnectionManagerSingleton(): void
    {
        $manager = TenantConnectionManager::getInstance();
        $this->assertInstanceOf(TenantConnectionManager::class, $manager);
    }

    public function testCRDTStockResolverMergeAndCalculate(): void
    {
        $counterA = [
            'sku' => 'SKU-001',
            'increments' => ['node1' => 10, 'node2' => 5],
            'decrements' => ['node1' => 2],
        ];

        $counterB = [
            'sku' => 'SKU-001',
            'increments' => ['node1' => 10, 'node2' => 15, 'node3' => 20],
            'decrements' => ['node1' => 2, 'node2' => 3],
        ];

        $merged = CRDTStockResolver::mergeCounters($counterA, $counterB);
        $this->assertEquals(10, $merged['increments']['node1']);
        $this->assertEquals(15, $merged['increments']['node2']);
        $this->assertEquals(20, $merged['increments']['node3']);

        $val = CRDTStockResolver::calculateValue($merged);
        $this->assertEquals((10 + 15 + 20) - (2 + 3), $val);
    }

    public function testRFIDBulkScanIngestionDeduplication(): void
    {
        $service = new RFIDBulkScanIngestionService();
        $scans = [
            ['epc' => 'EPC-TAG-1', 'sku' => 'SKU-A'],
            ['epc' => 'EPC-TAG-2', 'sku' => 'SKU-B'],
            ['epc' => 'EPC-TAG-1', 'sku' => 'SKU-A'], // Duplicate
        ];

        $result = $service->processBatch($scans);
        $this->assertEquals(3, $result['total_scans']);
        $this->assertEquals(2, $result['unique_processed']);
        $this->assertEquals(1, $result['duplicates_skipped']);
    }

    public function testAutonomousInventoryEngineEvaluation(): void
    {
        $engine = new AutonomousInventoryEngine('HUMAN_IN_THE_LOOP');
        $items = [
            ['sku' => 'SKU-LOW', 'current_stock' => 3, 'reorder_point' => 10],
            ['sku' => 'SKU-OK', 'current_stock' => 50, 'reorder_point' => 10],
        ];

        $actions = $engine->evaluateStockLevels($items);
        $this->assertCount(1, $actions);
        $this->assertEquals('SKU-LOW', $actions[0]['sku']);
        $this->assertEquals('DRAFT', $actions[0]['status']);
    }
}
