<?php

namespace App\Application\IoT;

class RFIDBulkScanIngestionService
{
    private array $epcBuffer = [];

    public function processBatch(array $scans): array
    {
        $startTime = microtime(true);
        $processed = 0;
        $duplicates = 0;

        foreach ($scans as $scan) {
            $epc = $scan['epc'] ?? null;
            if (!$epc) {
                continue;
            }

            if (isset($this->epcBuffer[$epc])) {
                $duplicates++;
            } else {
                $this->epcBuffer[$epc] = time();
                $processed++;
            }
        }

        $executionMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'total_scans' => count($scans),
            'unique_processed' => $processed,
            'duplicates_skipped' => $duplicates,
            'execution_time_ms' => $executionMs,
        ];
    }
}
