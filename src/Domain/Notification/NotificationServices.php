<?php

namespace App\Domain\Notification;

class NotificationDispatcherService {
    public array $outboxEvents = [];
    public array $inAppNotifications = [];

    public function dispatch(
        string $tenantId,
        array $targetUsers,
        string $category,
        string $severity,
        string $message,
        array $metadata,
        array $preferences
    ): void {
        foreach ($targetUsers as $userId) {
            $pref = $preferences[$userId] ?? null;
            if ($pref && $pref->isMuted && $severity !== NotificationSeverity::CRITICAL) {
                continue;
            }

            $channels = $pref ? $pref->channels : [NotificationChannel::IN_APP];

            if (in_array(NotificationChannel::IN_APP, $channels)) {
                $notif = new Notification(
                    uniqid(), $tenantId, $userId, $category, $severity, $message, $metadata
                );
                $this->inAppNotifications[] = $notif;
            }

            if (in_array(NotificationChannel::EMAIL, $channels)) {
                $this->outboxEvents[] = ['type' => 'SEND_EMAIL', 'userId' => $userId, 'message' => $message];
            }

            if (in_array(NotificationChannel::SMS, $channels)) {
                $this->outboxEvents[] = ['type' => 'SEND_SMS', 'userId' => $userId, 'message' => $message];
            }
        }
    }
}

class NotificationInboxService {
    public array $notifications;

    public function __construct(array $notifications) {
        $this->notifications = $notifications;
    }

    private function getNotification(string $notifId, string $userId): ?Notification {
        foreach ($this->notifications as $n) {
            if ($n->id === $notifId && $n->userId === $userId) {
                return $n;
            }
        }
        return null;
    }

    public function markAsRead(string $notifId, string $userId): bool {
        $n = $this->getNotification($notifId, $userId);
        if (!$n) return false;
        $n->markAsRead();
        return true;
    }

    public function snooze(string $notifId, string $userId, int $hours): bool {
        $n = $this->getNotification($notifId, $userId);
        if (!$n) return false;
        
        $until = new \DateTimeImmutable("+{$hours} hours");
        $n->snooze($until);
        return true;
    }

    public function escalate(string $notifId, string $userId, string $targetManagerId): ?Notification {
        $n = $this->getNotification($notifId, $userId);
        if (!$n) return null;
        
        $n->escalate();
        
        $escalatedNotif = new Notification(
            uniqid(), $n->tenantId, $targetManagerId, $n->category, 
            NotificationSeverity::CRITICAL, "[ESCALATED from {$userId}] {$n->message}", $n->metadata
        );
        $this->notifications[] = $escalatedNotif;
        
        return $escalatedNotif;
    }

    public function getUnreadCount(string $userId): int {
        $count = 0;
        foreach ($this->notifications as $n) {
            if ($n->userId === $userId && $n->status === NotificationStatus::UNREAD) {
                $count++;
            }
        }
        return $count;
    }
}
