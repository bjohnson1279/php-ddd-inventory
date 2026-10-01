<?php

namespace App\Domain\Notification;

class NotificationCategory {
    public const INVENTORY_LEVEL = 'INVENTORY_LEVEL';
    public const SYSTEM_ANOMALY = 'SYSTEM_ANOMALY';
    public const WEBHOOK_FAILURE = 'WEBHOOK_FAILURE';
    public const APPROVAL_REQUIRED = 'APPROVAL_REQUIRED';
}

class NotificationSeverity {
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const CRITICAL = 'CRITICAL';
}

class NotificationStatus {
    public const UNREAD = 'UNREAD';
    public const READ = 'READ';
    public const SNOOZED = 'SNOOZED';
    public const ESCALATED = 'ESCALATED';
}

class NotificationChannel {
    public const IN_APP = 'IN_APP';
    public const EMAIL = 'EMAIL';
    public const SMS = 'SMS';
    public const WEBHOOK = 'WEBHOOK';
}

class Notification {
    public string $id;
    public string $tenantId;
    public string $userId;
    public string $category;
    public string $severity;
    public string $message;
    public array $metadata;
    public string $status;
    public \DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $snoozedUntil;

    public function __construct(
        string $id,
        string $tenantId,
        string $userId,
        string $category,
        string $severity,
        string $message,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->category = $category;
        $this->severity = $severity;
        $this->message = $message;
        $this->metadata = $metadata;
        $this->status = NotificationStatus::UNREAD;
        $this->createdAt = new \DateTimeImmutable();
        $this->snoozedUntil = null;
    }

    public function markAsRead(): void {
        $this->status = NotificationStatus::READ;
    }

    public function snooze(\DateTimeImmutable $until): void {
        $this->status = NotificationStatus::SNOOZED;
        $this->snoozedUntil = $until;
    }

    public function escalate(): void {
        $this->status = NotificationStatus::ESCALATED;
        $this->severity = NotificationSeverity::CRITICAL;
    }
}

class NotificationPreference {
    public string $id;
    public string $tenantId;
    public string $userId;
    public string $category;
    public array $channels;
    public bool $isMuted;

    public function __construct(
        string $id,
        string $tenantId,
        string $userId,
        string $category,
        array $channels = [NotificationChannel::IN_APP],
        bool $isMuted = false
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->category = $category;
        $this->channels = $channels;
        $this->isMuted = $isMuted;
    }
}
