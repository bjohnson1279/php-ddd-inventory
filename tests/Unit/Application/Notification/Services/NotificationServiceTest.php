<?php

namespace Tests\Unit\Application\Notification\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Infrastructure\Models\NotificationModel;
use Illuminate\Database\Capsule\Manager as DB;

class NotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $capsule = new DB();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        DB::schema()->create('notifications', function ($table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function testCreateNotification(): void
    {
        $service = new NotificationService();
        $service->createNotification('tenant-1', 'Test Title', 'Test Message', 'info');

        $notifications = NotificationModel::where('tenant_id', 'tenant-1')->get();

        $this->assertCount(1, $notifications);
        $this->assertEquals('Test Title', $notifications[0]->title);
        $this->assertEquals('Test Message', $notifications[0]->message);
        $this->assertEquals('info', $notifications[0]->type);
        $this->assertFalse($notifications[0]->is_read);
    }

    public function testGetNotifications(): void
    {
        $service = new NotificationService();
        $service->createNotification('tenant-1', 'Title 1', 'Message 1', 'info');

        // Ensure some time passes for created_at sorting
        sleep(1);

        $service->createNotification('tenant-1', 'Title 2', 'Message 2', 'warning');
        $service->createNotification('tenant-2', 'Title 3', 'Message 3', 'error');

        $notifications = $service->getNotifications('tenant-1');

        $this->assertCount(2, $notifications);
        // Should be ordered by created_at desc
        $this->assertEquals('Title 2', $notifications[0]['title']);
        $this->assertEquals('Title 1', $notifications[1]['title']);
    }

    public function testMarkAsRead(): void
    {
        $service = new NotificationService();
        $service->createNotification('tenant-1', 'Test Title', 'Test Message', 'info');

        $notifications = $service->getNotifications('tenant-1');
        $notificationId = $notifications[0]['id'];

        $this->assertFalse($notifications[0]['is_read']);

        $service->markAsRead('tenant-1', $notificationId);

        $updatedNotifications = $service->getNotifications('tenant-1');
        $this->assertTrue($updatedNotifications[0]['is_read']);
    }

    public function testMarkAllAsRead(): void
    {
        $service = new NotificationService();
        $service->createNotification('tenant-1', 'Title 1', 'Message 1', 'info');
        $service->createNotification('tenant-1', 'Title 2', 'Message 2', 'warning');
        $service->createNotification('tenant-2', 'Title 3', 'Message 3', 'error');

        $service->markAllAsRead('tenant-1');

        $tenant1Notifications = $service->getNotifications('tenant-1');
        foreach ($tenant1Notifications as $notification) {
            $this->assertTrue($notification['is_read']);
        }

        $tenant2Notifications = $service->getNotifications('tenant-2');
        $this->assertFalse($tenant2Notifications[0]['is_read']);
    }
}
