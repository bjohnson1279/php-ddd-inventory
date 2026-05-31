<?php

namespace InventoryApp\Application\Notification\Services;

use InventoryApp\Infrastructure\Models\NotificationModel;
use Ramsey\Uuid\Uuid;

class NotificationService
{
    public function createNotification(string $tenantId, string $title, string $message, string $type = 'info'): void
    {
        NotificationModel::create([
            'id' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getNotifications(string $tenantId): array
    {
        return NotificationModel::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function markAsRead(string $tenantId, string $notificationId): void
    {
        NotificationModel::where('tenant_id', $tenantId)
            ->where('id', $notificationId)
            ->update(['is_read' => true]);
    }

    public function markAllAsRead(string $tenantId): void
    {
        NotificationModel::where('tenant_id', $tenantId)
            ->update(['is_read' => true]);
    }
}
