<?php

declare(strict_types=1);

namespace InventoryApp\Application\Receiving {
    class CurlMockState
    {
        public static ?string $response = null;
        public static int $httpCode = 200;
        public static array $lastOptFields = [];
        public static ?string $lastUrl = null;
        public static bool $shouldFailExec = false;

        public static function reset(): void
        {
            self::$response = null;
            self::$httpCode = 200;
            self::$lastOptFields = [];
            self::$lastUrl = null;
            self::$shouldFailExec = false;
        }
    }

    function curl_init(?string $url = null)
    {
        CurlMockState::$lastUrl = $url;
        return fopen('php://memory', 'r+');
    }

    function curl_setopt($ch, int $option, mixed $value): bool
    {
        CurlMockState::$lastOptFields[$option] = $value;
        return true;
    }

    function curl_exec($ch): string|bool
    {
        if (CurlMockState::$shouldFailExec) {
            return false;
        }
        return CurlMockState::$response ?? json_encode([
            'dimensions' => ['length' => 10.5, 'width' => 20.0, 'height' => 15.2],
            'anomaly_score' => 0.05,
            'has_damage' => false,
            'ocr_text' => 'SAMPLE OCR TEXT'
        ]);
    }

    function curl_getinfo($ch, int $opt = 0): mixed
    {
        if ($opt === CURLINFO_HTTP_CODE) {
            return CurlMockState::$httpCode;
        }
        return null;
    }

    function curl_close($ch): void
    {
        if (is_resource($ch)) {
            fclose($ch);
        }
    }
}

namespace Tests\Unit\Application\Receiving {
    use PHPUnit\Framework\TestCase;
    use InventoryApp\Application\Receiving\ReceivingService;
    use InventoryApp\Application\Receiving\CurlMockState;
    use InventoryApp\Domain\Receiving\InboundScan;
    use RuntimeException;

    class ReceivingServiceTest extends TestCase
    {
        private ReceivingService $service;

        protected function setUp(): void
        {
            parent::setUp();
            CurlMockState::reset();
            putenv('AI_SIDECAR_HOST');
            $this->service = new ReceivingService();
        }

        protected function tearDown(): void
        {
            putenv('AI_SIDECAR_HOST');
            parent::tearDown();
        }

        public function testAnalyzeInboundImageSuccessWithDefaultHost(): void
        {
            $tenantId = 'tenant-100';
            $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAS partitioning';
            $poId = 'PO-123';

            $scan = $this->service->analyzeInboundImage($tenantId, $base64Image, $poId);

            $this->assertInstanceOf(InboundScan::class, $scan);
            $this->assertSame('http://127.0.0.1:8000/cv/analyze-inbound', CurlMockState::$lastUrl);

            $postFields = json_decode(CurlMockState::$lastOptFields[CURLOPT_POSTFIELDS] ?? '', true);
            $this->assertSame($base64Image, $postFields['image_base64']);
            $this->assertSame($poId, $postFields['po_id']);

            $this->assertSame($tenantId, $scan->tenantId);
            $this->assertSame($poId, $scan->purchaseOrderId);
            $this->assertStringStartsWith('s3://mock-bucket/inbound/scan-', $scan->imageUrl);
            $this->assertSame(10.5, $scan->dimensions->length);
            $this->assertSame(20.0, $scan->dimensions->width);
            $this->assertSame(15.2, $scan->dimensions->height);
            $this->assertSame(0.05, $scan->anomalyScore);
            $this->assertFalse($scan->hasDamage);
            $this->assertSame('PENDING', $scan->status);
            $this->assertSame('SAMPLE OCR TEXT', $scan->ocrText);
            $this->assertInstanceOf(\DateTimeImmutable::class, $scan->createdAt);
            $this->assertInstanceOf(\DateTimeImmutable::class, $scan->updatedAt);
        }

        public function testAnalyzeInboundImageCustomSidecarHostAndNullPo(): void
        {
            putenv('AI_SIDECAR_HOST=http://ai-service.internal:9000');

            $tenantId = 'tenant-200';
            $base64Image = 'base64data';

            $scan = $this->service->analyzeInboundImage($tenantId, $base64Image, null);

            $this->assertInstanceOf(InboundScan::class, $scan);
            $this->assertSame('http://ai-service.internal:9000/cv/analyze-inbound', CurlMockState::$lastUrl);

            $postFields = json_decode(CurlMockState::$lastOptFields[CURLOPT_POSTFIELDS] ?? '', true);
            $this->assertSame($base64Image, $postFields['image_base64']);
            $this->assertNull($postFields['po_id']);

            $this->assertSame($tenantId, $scan->tenantId);
            $this->assertNull($scan->purchaseOrderId);
        }

        public function testAnalyzeInboundImageHandlesMissingOcrText(): void
        {
            CurlMockState::$response = json_encode([
                'dimensions' => ['length' => 5.0, 'width' => 5.0, 'height' => 5.0],
                'anomaly_score' => 0.0,
                'has_damage' => false
            ]);

            $scan = $this->service->analyzeInboundImage('tenant-1', 'image-data');

            $this->assertNull($scan->ocrText);
        }

        public function testAnalyzeInboundImageThrowsExceptionOnNon200HttpCode(): void
        {
            CurlMockState::$httpCode = 500;

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('AI Sidecar error: HTTP 500');

            $this->service->analyzeInboundImage('tenant-1', 'image-data');
        }

        public function testAnalyzeInboundImageThrowsExceptionOnCurlExecFailure(): void
        {
            CurlMockState::$shouldFailExec = true;

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('AI Sidecar error: HTTP 200');

            $this->service->analyzeInboundImage('tenant-1', 'image-data');
        }

        public function testApproveInboundScan(): void
        {
            $scanId = 'scan-999';

            $scan = $this->service->approveInboundScan($scanId);

            $this->assertInstanceOf(InboundScan::class, $scan);
            $this->assertSame($scanId, $scan->id);
            $this->assertSame('tenant-1', $scan->tenantId);
            $this->assertSame('APPROVED', $scan->status);
        }
    }
}
