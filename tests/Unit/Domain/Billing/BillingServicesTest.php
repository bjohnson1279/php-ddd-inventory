<?php

namespace Tests\Unit\Domain\Billing;

use PHPUnit\Framework\TestCase;
use App\Domain\Billing\TenantBillingTier;
use App\Domain\Billing\TierName;
use App\Domain\Billing\ApiUsageRecord;
use App\Domain\Billing\BillingEventType;
use App\Domain\Billing\RateLimitingService;
use App\Domain\Billing\UsageMeteringService;
use App\Domain\Billing\BillingHookService;

class BillingServicesTest extends TestCase
{
    public function testRateLimitingServiceHandlesTokenBucketCorrectly()
    {
        $service = new RateLimitingService();
        $tier = new TenantBillingTier(TierName::FREE, 60, 100, 1000, 0);

        $now = microtime(true);
        $r1 = $service->allowRequest($tier, 60.0, $now, $now);
        $this->assertTrue($r1['isAllowed']);
        $this->assertEquals(59.0, $r1['newTokens']);

        $r2 = $service->allowRequest($tier, 0.0, $now, $now);
        $this->assertFalse($r2['isAllowed']);

        $r3 = $service->allowRequest($tier, 0.0, $now, $now + 1.0);
        $this->assertTrue($r3['isAllowed']);
        $this->assertEquals(0.0, $r3['newTokens']);
    }

    public function testBillingHookServiceGeneratesOverageEvents()
    {
        $service = new BillingHookService();
        $tier = new TenantBillingTier(TierName::PRO, 60, 100, 1000, 0);

        $record = new ApiUsageRecord("T1", "2026-10", 1500, 0, 150);
        $events = $service->evaluateOverages($record, $tier, new \DateTimeImmutable());

        $this->assertCount(2, $events);

        $apiEvent = null;
        $skuEvent = null;

        foreach ($events as $e) {
            if ($e->eventType === BillingEventType::API_OVERAGE) $apiEvent = $e;
            if ($e->eventType === BillingEventType::NEW_SKU_TIER) $skuEvent = $e;
        }

        $this->assertNotNull($apiEvent);
        $this->assertEquals(500, $apiEvent->quantity);

        $this->assertNotNull($skuEvent);
        $this->assertEquals(50, $skuEvent->quantity);
    }
}
