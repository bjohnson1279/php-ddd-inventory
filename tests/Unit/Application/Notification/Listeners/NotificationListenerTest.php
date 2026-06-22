<?php

namespace Tests\Unit\Application\Notification\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Notification\Listeners\NotificationListener;
use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Domain\Inventory\Events\LowStockDetected;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use DateTimeImmutable;

class NotificationListenerTest extends TestCase
{
    private NotificationService $notificationServiceMock;

    protected function setUp(): void
    {
        $this->notificationServiceMock = $this->createMock(NotificationService::class);
    }

    public function testHandleLowStockWithConstructorTenantId(): void
    {
        $tenantId = 'test-tenant-id';
        $listener = new NotificationListener($this->notificationServiceMock, $tenantId);

        $sku = new SKU('TEST-SKU-123');
        $event = new LowStockDetected($sku, 5, 10, new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                $tenantId,
                'Low Stock Warning',
                "Variant with SKU 'TEST-SKU-123' is low on stock (5 remaining, threshold: 10).",
                'warning'
            );

        $listener->handleLowStock($event);
    }

    protected function tearDown(): void
    {
        if (isset($_SERVER['auth.tenant_id'])) {
            unset($_SERVER['auth.tenant_id']);
        }
        parent::tearDown();
    }

    public function testHandleLowStockWithServerTenantId(): void
    {
        $_SERVER['auth.tenant_id'] = 'server-tenant-id';
        $listener = new NotificationListener($this->notificationServiceMock);

        $sku = new SKU('TEST-SKU-456');
        $event = new LowStockDetected($sku, 2, 5, new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'server-tenant-id',
                'Low Stock Warning',
                "Variant with SKU 'TEST-SKU-456' is low on stock (2 remaining, threshold: 5).",
                'warning'
            );

        $listener->handleLowStock($event);
    }

    public function testHandleLowStockWithSystemTenantIdFallback(): void
    {
        // Ensure $_SERVER['auth.tenant_id'] is not set
        if (isset($_SERVER['auth.tenant_id'])) {
            unset($_SERVER['auth.tenant_id']);
        }

        $listener = new NotificationListener($this->notificationServiceMock);

        $sku = new SKU('TEST-SKU-789');
        $event = new LowStockDetected($sku, 0, 1, new DateTimeImmutable());

        $this->notificationServiceMock->expects($this->once())
            ->method('createNotification')
            ->with(
                'system',
                'Low Stock Warning',
                "Variant with SKU 'TEST-SKU-789' is low on stock (0 remaining, threshold: 1).",
                'warning'
            );

        $listener->handleLowStock($event);
    }
}
