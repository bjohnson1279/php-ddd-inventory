<?php

namespace Tests\Unit\Application\IoT;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\IoT\RFIDBulkScanIngestionService;

class RFIDBulkScanIngestionServiceTest extends TestCase
{
    private RFIDBulkScanIngestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RFIDBulkScanIngestionService();
    }

    public function testProcessEmptyBatch(): void
    {
        $result = $this->service->processBatch([]);

        $this->assertEquals(0, $result['total_scans']);
        $this->assertEquals(0, $result['unique_processed']);
        $this->assertEquals(0, $result['duplicates_skipped']);
        $this->assertArrayHasKey('execution_time_ms', $result);
    }

    public function testProcessUniqueScans(): void
    {
        $scans = [
            ['epc' => 'EPC-123', 'timestamp' => time()],
            ['epc' => 'EPC-456', 'timestamp' => time()],
        ];

        $result = $this->service->processBatch($scans);

        $this->assertEquals(2, $result['total_scans']);
        $this->assertEquals(2, $result['unique_processed']);
        $this->assertEquals(0, $result['duplicates_skipped']);
    }

    public function testProcessMissingEpcValuesSkipped(): void
    {
        $scans = [
            ['epc' => 'EPC-123'],
            ['timestamp' => time()], // Missing epc entirely
            ['epc' => null], // Null epc
            ['epc' => ''], // Empty epc
        ];

        $result = $this->service->processBatch($scans);

        // the empty, null and missing are all "not truthy" so they are skipped entirely by `if (!$epc) { continue; }`
        // they still count as "total_scans" but they are neither "processed" nor "duplicates"
        $this->assertEquals(4, $result['total_scans']);
        $this->assertEquals(1, $result['unique_processed']);
        $this->assertEquals(0, $result['duplicates_skipped']);
    }

    public function testProcessDuplicateScansInSameBatch(): void
    {
        $scans = [
            ['epc' => 'EPC-123'],
            ['epc' => 'EPC-123'],
            ['epc' => 'EPC-456'],
        ];

        $result = $this->service->processBatch($scans);

        $this->assertEquals(3, $result['total_scans']);
        $this->assertEquals(2, $result['unique_processed']);
        $this->assertEquals(1, $result['duplicates_skipped']);
    }

    public function testProcessDuplicateScansAcrossBatches(): void
    {
        $batch1 = [
            ['epc' => 'EPC-123'],
            ['epc' => 'EPC-456'],
        ];

        $batch2 = [
            ['epc' => 'EPC-456'], // Duplicate from previous batch
            ['epc' => 'EPC-789'], // New
        ];

        $result1 = $this->service->processBatch($batch1);

        $this->assertEquals(2, $result1['total_scans']);
        $this->assertEquals(2, $result1['unique_processed']);
        $this->assertEquals(0, $result1['duplicates_skipped']);

        $result2 = $this->service->processBatch($batch2);

        $this->assertEquals(2, $result2['total_scans']);
        $this->assertEquals(1, $result2['unique_processed']);
        $this->assertEquals(1, $result2['duplicates_skipped']);
    }
}
