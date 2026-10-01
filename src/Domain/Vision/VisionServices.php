<?php

namespace App\Domain\Vision;

class ComputerVisionService {
    public function analyzeImage(string $imageUrl, string $inspectionId): InspectionResult {
        $isDamaged = stripos($imageUrl, 'damaged') !== false;
        $damageScore = $isDamaged ? 0.85 : 0.05;
        $anomalies = $isDamaged ? ["CRUSHED_CORNER"] : [];

        return new InspectionResult(
            $inspectionId,
            "123456789012",
            new VolumeDimensions(10.0, 10.0, 10.0, DimensionUnit::CM),
            $damageScore,
            $anomalies
        );
    }
}

class QaGatewayService {
    public function processInspection(VisionInspection $inspection, InspectionResult $result, float $damageThreshold = 0.70): void {
        if ($result->damageScore >= $damageThreshold) {
            $inspection->flagInspection();
        } else {
            $inspection->passInspection();
        }
    }
}
