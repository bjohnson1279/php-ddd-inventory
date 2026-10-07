<?php

namespace Tests\Unit\Domain\Vision;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../src/Domain/Vision/VisionEntities.php';
require_once __DIR__ . '/../../../../src/Domain/Vision/VisionServices.php';
use App\Domain\Vision\VisionInspection;
use App\Domain\Vision\InspectionStatus;
use App\Domain\Vision\ComputerVisionService;
use App\Domain\Vision\QaGatewayService;

class VisionServicesTest extends TestCase
{
    public function testQaGatewayPassesGoodImage()
    {
        $cvService = new ComputerVisionService();
        $qaService = new QaGatewayService();

        $inspection = new VisionInspection(
            "INS1", "T1", "DOCK1", new \DateTimeImmutable(), "http://storage.com/image.jpg"
        );

        $result = $cvService->analyzeImage($inspection->imageUrl, $inspection->inspectionId);
        $qaService->processInspection($inspection, $result);

        $this->assertEquals(InspectionStatus::PASSED, $inspection->status);
        $this->assertEquals(0.05, $result->damageScore);
    }

    public function testQaGatewayFlagsDamagedImage()
    {
        $cvService = new ComputerVisionService();
        $qaService = new QaGatewayService();

        $inspection = new VisionInspection(
            "INS2", "T1", "DOCK1", new \DateTimeImmutable(), "http://storage.com/damaged_box.jpg"
        );

        $result = $cvService->analyzeImage($inspection->imageUrl, $inspection->inspectionId);
        $qaService->processInspection($inspection, $result, 0.5);

        $this->assertEquals(InspectionStatus::FLAGGED, $inspection->status);
        $this->assertEquals(0.85, $result->damageScore);
        $this->assertContains("CRUSHED_CORNER", $result->anomaliesDetected);
    }
}
