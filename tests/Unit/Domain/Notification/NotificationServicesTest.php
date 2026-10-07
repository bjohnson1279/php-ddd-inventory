<?php

namespace Tests\Unit\Domain\Notification;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../src/Domain/Notification/NotificationEntities.php';
require_once __DIR__ . '/../../../../src/Domain/Notification/NotificationServices.php';
use App\Domain\Notification\Notification;
use App\Domain\Notification\NotificationPreference;
use App\Domain\Notification\NotificationCategory;
use App\Domain\Notification\NotificationSeverity;
use App\Domain\Notification\NotificationChannel;
use App\Domain\Notification\NotificationDispatcherService;
use App\Domain\Notification\NotificationInboxService;

class NotificationServicesTest extends TestCase
{
    public function testDispatcherRoutesBasedOnPreferences()
    {
        $dispatcher = new NotificationDispatcherService();
        $pref = new NotificationPreference('p1', 't1', 'u1', NotificationCategory::INVENTORY_LEVEL, [NotificationChannel::EMAIL, NotificationChannel::IN_APP]);
        $prefs = ['u1' => $pref];

        $dispatcher->dispatch('t1', ['u1'], NotificationCategory::INVENTORY_LEVEL, NotificationSeverity::WARNING, 'msg', [], $prefs);

        $this->assertCount(1, $dispatcher->inAppNotifications);
        $this->assertCount(1, $dispatcher->outboxEvents);
        $this->assertEquals('SEND_EMAIL', $dispatcher->outboxEvents[0]['type']);
    }

    public function testInboxTracksUnreadAndSnooze()
    {
        $notif = new Notification('id1', 't1', 'u1', NotificationCategory::INVENTORY_LEVEL, NotificationSeverity::INFO, 'msg');
        $inbox = new NotificationInboxService([$notif]);

        $this->assertEquals(1, $inbox->getUnreadCount('u1'));

        $inbox->snooze('id1', 'u1', 24);
        $this->assertEquals('SNOOZED', $notif->status);
        $this->assertNotNull($notif->snoozedUntil);
    }
}
