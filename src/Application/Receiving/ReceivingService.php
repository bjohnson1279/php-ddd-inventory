<?php
declare(strict_types=1);

namespace InventoryApp\Application\Receiving;

use InventoryApp\Domain\Receiving\InboundScan;
use InventoryApp\Domain\Receiving\Dimensions;

class ReceivingService
{
    /**
     * Sends an image to the Python CV gateway and stores the resulting scan.
     */
    public function analyzeInboundImage(string $tenantId, string $base64Image, ?string $purchaseOrderId = null): InboundScan
    {
        $aiHost = getenv('AI_SIDECAR_HOST') ?: 'http://127.0.0.1:8000';
        
        // Mock cURL call to Python Sidecar
        $ch = curl_init("{$aiHost}/cv/analyze-inbound");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'image_base64' => $base64Image,
            'po_id' => $purchaseOrderId
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \RuntimeException("AI Sidecar error: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        
        $id = 'scan-' . bin2hex(random_bytes(4));
        
        $scan = new InboundScan(
            $id,
            $tenantId,
            $purchaseOrderId,
            's3://mock-bucket/inbound/' . $id . '.jpg',
            new Dimensions((float)$data['dimensions']['length'], (float)$data['dimensions']['width'], (float)$data['dimensions']['height']),
            (float)$data['anomaly_score'],
            (bool)$data['has_damage'],
            'PENDING',
            $data['ocr_text'] ?? null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        
        // In a real application, we would persist this to the SQLite database
        
        return $scan;
    }

    public function approveInboundScan(string $scanId): InboundScan
    {
        // Mock fetch from DB
        $scan = new InboundScan(
            $scanId,
            'tenant-1',
            null,
            's3://mock-bucket/inbound/' . $scanId . '.jpg',
            new Dimensions(10.0, 10.0, 10.0),
            0.1,
            false,
            'PENDING',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $scan->approve();
        // Mock save to DB
        return $scan;
    }
}
