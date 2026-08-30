<?php

namespace App\Domain\Notification;

class Notification
{
    private string $id;
    private string $tenantId;
    private string $userId;
    private string $type;
    private string $message;
    private bool $isRead;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $tenantId,
        string $userId,
        string $type,
        string $message,
        bool $isRead,
        \DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->type = $type;
        $this->message = $message;
        $this->isRead = $isRead;
        $this->createdAt = $createdAt;
    }

    public function getId(): string { return $this->id; }
}
