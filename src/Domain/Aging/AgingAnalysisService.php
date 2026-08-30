<?php

namespace App\Domain\Aging;

class AgingAnalysisService
{
    public function generateAgingReport(array $inventoryRecords): array
    {
        return [
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'buckets' => []
        ];
    }
}
