<?php

namespace Tests\Unit\Application\IoT;

use InventoryApp\Application\IoT\RFIDBulkScanIngestionService;
use PHPUnit\Framework\TestCase;

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

    public function testProcessValidUniqueScans(): void
    {
        $scans = [
            ['epc' => 'EPC_123'],
            ['epc' => 'EPC_456'],
        ];

        $result = $this->service->processBatch($scans);

        $this->assertEquals(2, $result['total_scans']);
        $this->assertEquals(2, $result['unique_processed']);
        $this->assertEquals(0, $result['duplicates_skipped']);
    }

    public function testProcessDuplicatesInSameBatch(): void
    {
        $scans = [
            ['epc' => 'EPC_123'],
            ['epc' => 'EPC_123'],
            ['epc' => 'EPC_456'],
        ];

        $result = $this->service->processBatch($scans);

        $this->assertEquals(3, $result['total_scans']);
        $this->assertEquals(2, $result['unique_processed']);
        $this->assertEquals(1, $result['duplicates_skipped']);
    }

    public function testProcessMissingOrNullEpc(): void
    {
        $scans = [
            ['epc' => null],
            ['other_key' => 'value'],
        ];

        $result = $this->service->processBatch($scans);

        $this->assertEquals(2, $result['total_scans']);
        $this->assertEquals(0, $result['unique_processed']);
        $this->assertEquals(0, $result['duplicates_skipped']);
    }

    public function testStateRetentionAcrossBatches(): void
    {
        $batch1 = [
            ['epc' => 'EPC_123'],
        ];

        $result1 = $this->service->processBatch($batch1);

        $this->assertEquals(1, $result1['total_scans']);
        $this->assertEquals(1, $result1['unique_processed']);
        $this->assertEquals(0, $result1['duplicates_skipped']);

        $batch2 = [
            ['epc' => 'EPC_123'],
        ];

        $result2 = $this->service->processBatch($batch2);

        $this->assertEquals(1, $result2['total_scans']);
        $this->assertEquals(0, $result2['unique_processed']);
        $this->assertEquals(1, $result2['duplicates_skipped']);
    }
}
