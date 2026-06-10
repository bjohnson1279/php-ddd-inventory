<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Notification\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Infrastructure\Models\NotificationModel;

require_once __DIR__ . '/../../../bootstrap.php';

/** @group integration */
final class NotificationServiceTest extends TestCase
{
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear notifications table before each test to ensure a clean state
        \Illuminate\Database\Capsule\Manager::table('notifications')->delete();
        $this->service = new NotificationService();
    }

    public function test_create_notification(): void
    {
        $tenantId = 'test-tenant';
        $title = 'Test Title';
        $message = 'Test Message';
        $type = 'success';

        $this->service->createNotification($tenantId, $title, $message, $type);

        $notifications = NotificationModel::where('tenant_id', $tenantId)->get();
        $this->assertCount(1, $notifications);

        $notification = $notifications->first();
        $this->assertEquals($title, $notification->title);
        $this->assertEquals($message, $notification->message);
        $this->assertEquals($type, $notification->type);
        $this->assertFalse($notification->is_read);
    }

    public function test_get_notifications(): void
    {
        $tenantId = 'test-tenant';
        $this->service->createNotification($tenantId, 'Title 1', 'Message 1');

        sleep(1);

        $this->service->createNotification($tenantId, 'Title 2', 'Message 2', 'warning');

        $notifications = $this->service->getNotifications($tenantId);
        $this->assertCount(2, $notifications);
        $this->assertIsArray($notifications);

        $this->assertEquals('Title 2', $notifications[0]['title']);
        $this->assertEquals('test-tenant', $notifications[0]['tenant_id']);
        $this->assertEquals('Title 1', $notifications[1]['title']);
        $this->assertEquals('test-tenant', $notifications[1]['tenant_id']);
    }

    public function test_mark_as_read(): void
    {
        $tenantId = 'test-tenant';
        $this->service->createNotification($tenantId, 'Title 1', 'Message 1');

        $notifications = $this->service->getNotifications($tenantId);
        $this->assertCount(1, $notifications);
        $this->assertFalse((bool)$notifications[0]['is_read']);

        $notificationId = $notifications[0]['id'];
        $this->service->markAsRead($tenantId, $notificationId);

        $updatedNotifications = $this->service->getNotifications($tenantId);
        $this->assertTrue((bool)$updatedNotifications[0]['is_read']);
    }

    public function test_mark_all_as_read(): void
    {
        $tenantId = 'test-tenant';
        $this->service->createNotification($tenantId, 'Title 1', 'Message 1');
        $this->service->createNotification($tenantId, 'Title 2', 'Message 2');

        $notifications = $this->service->getNotifications($tenantId);
        $this->assertCount(2, $notifications);
        $this->assertFalse((bool)$notifications[0]['is_read']);
        $this->assertFalse((bool)$notifications[1]['is_read']);

        $this->service->markAllAsRead($tenantId);

        $updatedNotifications = $this->service->getNotifications($tenantId);
        $this->assertTrue((bool)$updatedNotifications[0]['is_read']);
        $this->assertTrue((bool)$updatedNotifications[1]['is_read']);
    }
}
