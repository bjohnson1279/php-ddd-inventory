<?php

namespace Tests\Unit\Domain\CycleCount;

use PHPUnit\Framework\TestCase;
use App\Domain\CycleCount\CycleCountLineItem;
use App\Domain\CycleCount\CycleCountExecutionService;

class CycleCountExecutionServiceTest extends TestCase
{
    public function testProcessSubmissionMatchesCorrectly()
    {
        $service = new CycleCountExecutionService();
        $item = new CycleCountLineItem('id1', 'cc1', 'SKU-1', 100);

        $success = $service->processSubmission([$item], ['SKU-1' => 100]);

        $this->assertTrue($success);
        $this->assertEquals('MATCHED', $item->getStatus());
        $this->assertEquals(0, $item->getVarianceQuantity());
    }

    public function testProcessSubmissionFlagsVariance()
    {
        $service = new CycleCountExecutionService();
        $item = new CycleCountLineItem('id1', 'cc1', 'SKU-1', 100);

        // 90 counted, 100 expected => 10% variance (threshold default 5%)
        $success = $service->processSubmission([$item], ['SKU-1' => 90]);

        $this->assertFalse($success);
        $this->assertEquals('VARIANCE_FLAGGED', $item->getStatus());
        $this->assertEquals(-10, $item->getVarianceQuantity());
    }
}
