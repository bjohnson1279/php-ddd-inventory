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

    protected function tearDown(): void
    {
        unset($_SERVER['auth.tenant_id']);
        parent::tearDown();
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

    public function testHandleStockReceivedWithServerTenantId(): void
    {
        $_SERVER['auth.tenant_id'] = 'server-tenant-123';
        $listener = new NotificationListener($this->notificationServiceMock, null);

        $sku = new SKU('SKU-201');
        $locationId = new LocationId('LOC-SUB');
        $event = new StockReceived($sku, $locationId, 10, 'REF-456', new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'server-tenant-123',
                'Stock Received',
                "Received 10 units for SKU 'SKU-201' at location 'LOC-SUB'.",
                'success'
            );

        $listener->handleStockReceived($event);
    }

    public function testHandleStockReceivedWithSystemTenantId(): void
    {
        $listener = new NotificationListener($this->notificationServiceMock, null);

        $sku = new SKU('SKU-202');
        $locationId = new LocationId('LOC-SYS');
        $event = new StockReceived($sku, $locationId, 20, 'REF-789', new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'system',
                'Stock Received',
                "Received 20 units for SKU 'SKU-202' at location 'LOC-SYS'.",
                'success'
            );

        $listener->handleStockReceived($event);
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

    public function testHandleOnboardingSubmittedResolvesTenantFromGlobal(): void
    {
        $listenerWithoutTenant = new NotificationListener($this->notificationServiceMock, null);
        $_SERVER['auth.tenant_id'] = 'global-tenant';

        $event = new StockOnboardingSubmitted(
            'onboarding-2',
            'global-tenant',
            'LOC-MAIN',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'global-tenant',
                'Inventory Onboarding Submitted',
                'A draft stock onboarding batch was submitted for validation.',
                'info'
            );

        $listenerWithoutTenant->handleOnboardingSubmitted($event);
    }

    public function testHandleOnboardingSubmittedFallsBackToSystem(): void
    {
        $listenerWithoutTenant = new NotificationListener($this->notificationServiceMock, null);
        unset($_SERVER['auth.tenant_id']);

        $event = new StockOnboardingSubmitted(
            'onboarding-3',
            'system',
            'LOC-MAIN',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'system',
                'Inventory Onboarding Submitted',
                'A draft stock onboarding batch was submitted for validation.',
                'info'
            );

        $listenerWithoutTenant->handleOnboardingSubmitted($event);
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

    public function testHandleStockReconciledWithServerTenantId(): void
    {
        $listenerWithoutTenant = new NotificationListener($this->notificationServiceMock, null);
        $_SERVER['auth.tenant_id'] = 'server-tenant';

        $sku = new SKU('SKU-301');
        $locationId = new LocationId('LOC-MAIN');
        $event = new StockReconciled($sku, $locationId, 100, -5, 'REF-789', new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'server-tenant',
                'Stock Reconciled',
                "Physical count reconciliation adjusted stock for SKU 'SKU-301' to 100.",
                'info'
            );

        $listenerWithoutTenant->handleStockReconciled($event);
    }

    public function testHandleStockReconciledFallsBackToSystem(): void
    {
        $listenerWithoutTenant = new NotificationListener($this->notificationServiceMock, null);
        unset($_SERVER['auth.tenant_id']);

        $sku = new SKU('SKU-302');
        $locationId = new LocationId('LOC-MAIN');
        $event = new StockReconciled($sku, $locationId, 200, 20, 'REF-999', new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'system',
                'Stock Reconciled',
                "Physical count reconciliation adjusted stock for SKU 'SKU-302' to 200.",
                'info'
            );

        $listenerWithoutTenant->handleStockReconciled($event);
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
}
