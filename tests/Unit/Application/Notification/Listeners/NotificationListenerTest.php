<?php

namespace Tests\Unit\Application\Notification\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Notification\Listeners\NotificationListener;
use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Domain\Inventory\Events\LowStockDetected;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted;
use InventoryApp\Domain\Inventory\Events\StockReconciled;
use InventoryApp\Domain\Inventory\Events\OpeningBalancePosted;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;

class NotificationListenerTest extends TestCase
{

    private NotificationService $notificationServiceMock;
    private NotificationListener $listener;
    private string $tenantId = 'tenant-123';

    protected function setUp(): void
    {
        $this->notificationServiceMock = $this->createMock(NotificationService::class);
        $this->listener = new NotificationListener($this->notificationServiceMock, $this->tenantId);
    }

    public function testHandleLowStock(): void
    {
        $sku = new SKU('SKU-100');
        $event = new LowStockDetected($sku, 5, 10, new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                $this->tenantId,
                'Low Stock Warning',
                "Variant with SKU 'SKU-100' is low on stock (5 remaining, threshold: 10).",
                'warning'
            );

        $this->listener->handleLowStock($event);
    }

    public function testHandleStockReceived(): void
    {
        $sku = new SKU('SKU-200');
        $locationId = new LocationId('LOC-MAIN');
        $event = new StockReceived($sku, $locationId, 50, 'REF-123', new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                $this->tenantId,
                'Stock Received',
                "Received 50 units for SKU 'SKU-200' at location 'LOC-MAIN'.",
                'success'
            );

        $this->listener->handleStockReceived($event);
    }

    public function testHandleOnboardingSubmitted(): void
    {
        $event = new StockOnboardingSubmitted(
            'onboarding-1',
            $this->tenantId,
            'LOC-MAIN',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                $this->tenantId,
                'Inventory Onboarding Submitted',
                'A draft stock onboarding batch was submitted for validation.',
                'info'
            );

        $this->listener->handleOnboardingSubmitted($event);
    }

    public function testHandleStockReconciled(): void
    {
        $sku = new SKU('SKU-300');
        $locationId = new LocationId('LOC-MAIN');
        $event = new StockReconciled($sku, $locationId, 150, 10, 'REF-456', new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                $this->tenantId,
                'Stock Reconciled',
                "Physical count reconciliation adjusted stock for SKU 'SKU-300' to 150.",
                'info'
            );

        $this->listener->handleStockReconciled($event);
    }

    public function testHandleOpeningBalancePosted(): void
    {
        $event = new OpeningBalancePosted(
            'onboarding-1',
            'var-1',
            100,
            5000,
            'LOC-MAIN',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                $this->tenantId,
                'Opening Balances Posted',
                'Opening inventory has been finalized and posted to the ledger.',
                'success'
            );

        $this->listener->handleOpeningBalancePosted($event);
    }

    public function testHandleStockReceivedCreatesNotification(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $tenantId = 'test-tenant';

        $listener = new NotificationListener($notificationService, $tenantId);

        $sku = new SKU('PROD-123');
        $locationId = new LocationId('LOC-STORE');
        $quantity = 50;
        $reference = 'PO-999';
        $occurredOn = new DateTimeImmutable();

        $event = new StockReceived(
            $sku,
            $locationId,
            $quantity,
            $reference,
            $occurredOn
        );

        $notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                $tenantId,
                'Stock Received',
                "Received {$quantity} units for SKU '{$sku->getValue()}' at location '{$locationId->getValue()}'.",
                'success'
            );

        $listener->handleStockReceived($event);

    }
}
