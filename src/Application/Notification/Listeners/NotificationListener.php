<?php

namespace InventoryApp\Application\Notification\Listeners;

use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Domain\Inventory\Events\LowStockDetected;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted;
use InventoryApp\Domain\Inventory\Events\StockReconciled;
use InventoryApp\Domain\Inventory\Events\OpeningBalancePosted;

class NotificationListener
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ?string $tenantId = null
    ) {}

    private function resolveTenantId(): string
    {
        return $this->tenantId ?? $_SERVER['auth.tenant_id'] ?? 'system';
    }

    public function handleLowStock(LowStockDetected $event): void
    {
        $this->notificationService->createNotification(
            $this->resolveTenantId(),
            "Low Stock Warning",
            "Variant with SKU '{$event->getSku()->getValue()}' is low on stock ({$event->currentQuantity} remaining, threshold: {$event->threshold}).",
            'warning'
        );
    }

    public function handleStockReceived(StockReceived $event): void
    {
        $this->notificationService->createNotification(
            $this->resolveTenantId(),
            "Stock Received",
            "Received {$event->quantity} units for SKU '{$event->getSku()->getValue()}' at location '{$event->getLocationId()->getValue()}'.",
            'success'
        );
    }

    public function handleOnboardingSubmitted(StockOnboardingSubmitted $event): void
    {
        $this->notificationService->createNotification(
            $this->resolveTenantId(),
            "Inventory Onboarding Submitted",
            "A draft stock onboarding batch was submitted for validation.",
            'info'
        );
    }

    public function handleStockReconciled(StockReconciled $event): void
    {
        $this->notificationService->createNotification(
            $this->resolveTenantId(),
            "Stock Reconciled",
            "Physical count reconciliation adjusted stock for SKU '{$event->getSku()->getValue()}' to {$event->actualQuantity}.",
            'info'
        );
    }

    public function handleOpeningBalancePosted(OpeningBalancePosted $event): void
    {
        $this->notificationService->createNotification(
            $this->resolveTenantId(),
            "Opening Balances Posted",
            "Opening inventory has been finalized and posted to the ledger.",
            'success'
        );
    }
}
