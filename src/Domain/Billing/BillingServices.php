<?php

namespace App\Domain\Billing;

class RateLimitingService {
    public function allowRequest(
        TenantBillingTier $tier,
        float $currentTokens,
        float $lastRefillTime,
        float $currentTime = null
    ): array {
        if ($currentTime === null) {
            $currentTime = microtime(true);
        }

        $capacity = $tier->maxRequestsPerMinute;
        $refillRate = $capacity / 60.0;

        $elapsed = max(0, $currentTime - $lastRefillTime);
        $tokensToAdd = $elapsed * $refillRate;

        $newTokens = min($capacity, $currentTokens + $tokensToAdd);

        if ($newTokens >= 1.0) {
            return [
                'isAllowed' => true,
                'newTokens' => $newTokens - 1.0,
                'newLastRefillTime' => $currentTime
            ];
        } else {
            return [
                'isAllowed' => false,
                'newTokens' => $newTokens,
                'newLastRefillTime' => $currentTime
            ];
        }
    }
}

class UsageMeteringService {
    public function incrementApiUsage(ApiUsageRecord $record): void {
        $record->apiRequestsCount++;
    }

    public function recordStorageUsage(ApiUsageRecord $record, int $bytesUsed): void {
        if ($bytesUsed > $record->storageBytesUsed) {
            $record->storageBytesUsed = $bytesUsed;
        }
    }

    public function updateActiveSkus(ApiUsageRecord $record, int $skuCount): void {
        $record->activeSkusCount = $skuCount;
    }
}

class BillingHookService {
    public function evaluateOverages(
        ApiUsageRecord $record,
        TenantBillingTier $tier,
        \DateTimeImmutable $currentTime = null
    ): array {
        if ($currentTime === null) {
            $currentTime = new \DateTimeImmutable();
        }

        $events = [];

        if ($record->apiRequestsCount > $tier->includedApiRequestsPerMonth) {
            $overage = $record->apiRequestsCount - $tier->includedApiRequestsPerMonth;
            $events[] = new BillingEvent(
                uniqid(),
                $record->tenantId,
                BillingEventType::API_OVERAGE,
                $overage,
                $currentTime
            );
        }

        if ($record->activeSkusCount > $tier->maxActiveSkus) {
            $overage = $record->activeSkusCount - $tier->maxActiveSkus;
            $events[] = new BillingEvent(
                uniqid(),
                $record->tenantId,
                BillingEventType::NEW_SKU_TIER,
                $overage,
                $currentTime
            );
        }

        return $events;
    }
}
