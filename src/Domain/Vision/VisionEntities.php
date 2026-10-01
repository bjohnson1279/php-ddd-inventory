<?php

namespace App\Domain\Vision;

class InspectionStatus {
    public const PENDING = 'PENDING';
    public const ANALYZED = 'ANALYZED';
    public const FLAGGED = 'FLAGGED';
    public const PASSED = 'PASSED';
}

class DimensionUnit {
    public const CM = 'CM';
    public const INCH = 'INCH';
}

class VolumeDimensions {
    public float $length;
    public float $width;
    public float $height;
    public string $unit;

    public function __construct(float $length, float $width, float $height, string $unit) {
        $this->length = $length;
        $this->width = $width;
        $this->height = $height;
        $this->unit = $unit;
    }
}

class InspectionResult {
    public string $inspectionId;
    public string $detectedBarcode;
    public VolumeDimensions $dimensions;
    public float $damageScore;
    public array $anomaliesDetected;

    public function __construct(
        string $inspectionId,
        string $detectedBarcode,
        VolumeDimensions $dimensions,
        float $damageScore,
        array $anomaliesDetected = []
    ) {
        $this->inspectionId = $inspectionId;
        $this->detectedBarcode = $detectedBarcode;
        $this->dimensions = $dimensions;
        $this->damageScore = $damageScore;
        $this->anomaliesDetected = $anomaliesDetected;
    }
}

class VisionInspection {
    public string $inspectionId;
    public string $tenantId;
    public string $dockStationId;
    public \DateTimeImmutable $capturedAt;
    public string $imageUrl;
    public string $status;

    public function __construct(
        string $inspectionId,
        string $tenantId,
        string $dockStationId,
        \DateTimeImmutable $capturedAt,
        string $imageUrl,
        string $status = InspectionStatus::PENDING
    ) {
        $this->inspectionId = $inspectionId;
        $this->tenantId = $tenantId;
        $this->dockStationId = $dockStationId;
        $this->capturedAt = $capturedAt;
        $this->imageUrl = $imageUrl;
        $this->status = $status;
    }

    public function passInspection(): void {
        if ($this->status !== InspectionStatus::PENDING && $this->status !== InspectionStatus::ANALYZED) {
            throw new \Exception("Invalid status transition to PASSED");
        }
        $this->status = InspectionStatus::PASSED;
    }

    public function flagInspection(): void {
        if ($this->status !== InspectionStatus::PENDING && $this->status !== InspectionStatus::ANALYZED) {
            throw new \Exception("Invalid status transition to FLAGGED");
        }
        $this->status = InspectionStatus::FLAGGED;
    }
}
