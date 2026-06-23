<?php

namespace Tests\Unit\Application\Notification\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Notification\Listeners\NotificationListener;
use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;

class NotificationListenerTest extends TestCase
{
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

    public function testHandleOnboardingSubmittedCreatesNotification(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $listener = new NotificationListener($notificationService, 'tenant-123');

        $event = new StockOnboardingSubmitted(
            'onboarding-1',
            'tenant-123',
            'location-1',
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                'tenant-123',
                "Inventory Onboarding Submitted",
                "A draft stock onboarding batch was submitted for validation.",
                'info'
            );

        $listener->handleOnboardingSubmitted($event);
    }
}
