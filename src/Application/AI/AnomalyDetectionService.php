<?php

namespace InventoryApp\Application\AI;

use Illuminate\Database\Capsule\Manager as DB;

class AnomalyDetectionService
{
    private string $sidecarUrl;

    public function __construct()
    {
        $this->sidecarUrl = getenv('PYTHON_SIDECAR_URL') ?: 'http://localhost:5005';
    }

    public function analyze(string $tenantId): array
    {
        // 1. Gather ledger entries from DB
        $ledgerEntries = DB::table('ledger_entries')
            ->where('tenant_id', $tenantId)
            ->orderBy('occurred_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        // 2. Format for sidecar
        $sidecarLedger = array_map(function ($e) {
            return [
                'sku' => $e->variant_id ?? '',
                'location_id' => $e->location_id ?? '',
                'quantity' => $e->quantity ?? 0,
                'reason' => $e->reason ?? 'unknown',
                'actor_id' => $e->actor_id ?? 'system',
                'occurred_at' => $e->occurred_at ?? date('c'),
                'reference_id' => $e->reference_id ?? null,
            ];
        }, $ledgerEntries);

        // 3. Derive cycle counts from count_adjustment entries
        $cycleCounts = array_values(array_filter(array_map(function ($e) {
            if (($e->reason ?? '') === 'count_adjustment') {
                return [
                    'sku' => $e->variant_id ?? '',
                    'location_id' => $e->location_id ?? '',
                    'expected_quantity' => 0,
                    'counted_quantity' => $e->quantity ?? 0,
                    'counted_at' => $e->occurred_at ?? date('c'),
                    'actor_id' => $e->actor_id ?? 'system',
                ];
            }
            return null;
        }, $ledgerEntries)));

        $payload = json_encode([
            'ledger_entries' => $sidecarLedger,
            'cycle_counts' => $cycleCounts,
            'scan_events' => [],
        ]);

        // 4. POST to Python sidecar
        $url = $this->sidecarUrl . '/anomaly-detect';
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            return $this->fallback();
        }

        $decoded = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->fallback();
        }

        // 5. Map snake_case response to camelCase for API consistency
        return [
            'alerts' => array_map(function ($a) {
                return [
                    'alertType' => $a['alert_type'] ?? '',
                    'severity' => $a['severity'] ?? 'LOW',
                    'confidence' => $a['confidence'] ?? 0,
                    'sku' => $a['sku'] ?? null,
                    'locationId' => $a['location_id'] ?? null,
                    'actorId' => $a['actor_id'] ?? null,
                    'title' => $a['title'] ?? '',
                    'description' => $a['description'] ?? '',
                    'evidence' => $a['evidence'] ?? [],
                    'detectedAt' => $a['detected_at'] ?? date('c'),
                ];
            }, $decoded['alerts'] ?? []),
            'totalCritical' => $decoded['summary']['total_critical'] ?? 0,
            'totalHigh' => $decoded['summary']['total_high'] ?? 0,
            'totalMedium' => $decoded['summary']['total_medium'] ?? 0,
            'totalLow' => $decoded['summary']['total_low'] ?? 0,
            'overallRiskScore' => $decoded['summary']['overall_risk_score'] ?? 0,
        ];
    }

    private function fallback(): array
    {
        return [
            'alerts' => [],
            'totalCritical' => 0,
            'totalHigh' => 0,
            'totalMedium' => 0,
            'totalLow' => 0,
            'overallRiskScore' => 0,
        ];
    }
}
