<?php

namespace Tests\Unit\Domain\Reporting;

use PHPUnit\Framework\TestCase;
use App\Domain\Reporting\ReportExecutionService;
use App\Domain\Reporting\ReportScheduler;

class ReportExecutionServiceTest extends TestCase
{
    public function testGenerateReport()
    {
        $service = new ReportExecutionService();
        $url = $service->generateReport('r1', 'csv');
        $this->assertStringContainsString('r1', $url);
        $this->assertStringEndsWith('.csv', $url);

        $urlPdf = $service->generateReport('r1', 'pdf');
        $this->assertStringEndsWith('.pdf', $urlPdf);
    }

    public function testGenerateReportThrowsOnInvalidFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $service = new ReportExecutionService();
        $service->generateReport('r1', 'xml');
    }

    public function testScheduleReport()
    {
        $scheduler = new ReportScheduler();
        $id = $scheduler->scheduleReport('r1', '0 0 * * *', 'email');
        $this->assertStringStartsWith('sched_', $id);
    }
}
