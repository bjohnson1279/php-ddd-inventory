<?php

namespace Tests\Unit\Application\Notification\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Notification\Listeners\NotificationListener;
use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted;
use DateTimeImmutable;

class NotificationListenerTest extends TestCase
{
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
