<?php

namespace App\Domain\Billing;

class TierName {
    public const FREE = 'FREE';
    public const PRO = 'PRO';
    public const ENTERPRISE = 'ENTERPRISE';
}

class BillingEventType {
    public const API_OVERAGE = 'API_OVERAGE';
    public const STORAGE_OVERAGE = 'STORAGE_OVERAGE';
    public const NEW_SKU_TIER = 'NEW_SKU_TIER';
}

class TenantBillingTier {
    public string $tierName;
    public int $maxRequestsPerMinute;
    public int $maxActiveSkus;
    public int $includedApiRequestsPerMonth;
    public int $baseMonthlyPriceCents;

    public function __construct(
        string $tierName,
        int $maxRequestsPerMinute,
        int $maxActiveSkus,
        int $includedApiRequestsPerMonth,
        int $baseMonthlyPriceCents
    ) {
        $this->tierName = $tierName;
        $this->maxRequestsPerMinute = $maxRequestsPerMinute;
        $this->maxActiveSkus = $maxActiveSkus;
        $this->includedApiRequestsPerMonth = $includedApiRequestsPerMonth;
        $this->baseMonthlyPriceCents = $baseMonthlyPriceCents;
    }
}

class ApiUsageRecord {
    public string $tenantId;
    public string $billingCycleId;
    public int $apiRequestsCount;
    public int $storageBytesUsed;
    public int $activeSkusCount;

    public function __construct(
        string $tenantId,
        string $billingCycleId,
        int $apiRequestsCount,
        int $storageBytesUsed,
        int $activeSkusCount
    ) {
        $this->tenantId = $tenantId;
        $this->billingCycleId = $billingCycleId;
        $this->apiRequestsCount = $apiRequestsCount;
        $this->storageBytesUsed = $storageBytesUsed;
        $this->activeSkusCount = $activeSkusCount;
    }
}

class BillingEvent {
    public string $eventId;
    public string $tenantId;
    public string $eventType;
    public int $quantity;
    public \DateTimeImmutable $occurredAt;

    public function __construct(
        string $eventId,
        string $tenantId,
        string $eventType,
        int $quantity,
        \DateTimeImmutable $occurredAt
    ) {
        $this->eventId = $eventId;
        $this->tenantId = $tenantId;
        $this->eventType = $eventType;
        $this->quantity = $quantity;
        $this->occurredAt = $occurredAt;
    }
}
