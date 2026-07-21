<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap database capsule
$capsule = require_once __DIR__ . '/../src/Infrastructure/Persistence/bootstrap_database.php';

use InventoryApp\Application\Webhooks\Workers\WebhookDeliveryWorker;

$once = in_array('--once', $argv);

$worker = new WebhookDeliveryWorker();
$worker->run($once);



use InventoryApp\Infrastructure\Models\WebhookSubscriptionModel;
use InventoryApp\Infrastructure\Models\WebhookDeliveryModel;


echo "Starting DDD Webhook Delivery Worker...\n";

do {
    $now = new \DateTime();
    $deliveries = WebhookDeliveryModel::where('status', 'Pending')
        ->where(function ($query) use ($now) {
            $query->whereNull('next_attempt_at')
                  ->orWhere('next_attempt_at', '<=', $now);
        })
        ->orderBy('created_at', 'asc')
        ->take(10)
        ->get();

    if ($deliveries->isEmpty()) {
        if ($once) {
            echo "No pending webhooks found. Exiting.\n";
            break;
        }
        usleep(2000000); // 2s
        continue;
    }

    // Mark as Processing in batch
    $ids = $deliveries->pluck('id')->toArray();
    WebhookDeliveryModel::whereIn('id', $ids)->update(['status' => 'Processing']);

    foreach ($deliveries as $delivery) {
        $delivery->status = 'Processing';
        $delivery->syncOriginal();
        echo "Processing Webhook Delivery ID: {$delivery->id}...\n";

        try {
            $subscription = WebhookSubscriptionModel::find($delivery->subscription_id);
            if (!$subscription || !$subscription->is_active) {
                throw new \Exception("Subscription not found or inactive: {$delivery->subscription_id}");
            }

            // Calculate HMAC-SHA256 signature
            $signature = hash_hmac('sha256', $delivery->payload, $subscription->secret);

            // Execute POST request via curl
            $ch = curl_init($subscription->target_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $delivery->payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Webhook-Signature-256: ' . $signature,
                'X-Webhook-Event: ' . $delivery->event_type
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \Exception("cURL Error: " . $curlError);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new \Exception("HTTP Error Status: " . $httpCode);
            }

            // Mark as Success
            $delivery->status = 'Success';
            $delivery->attempts = $delivery->attempts + 1;
            $delivery->processed_at = new \DateTime();
            $delivery->save();

            echo "Webhook delivery {$delivery->id} sent successfully.\n";
        } catch (\Throwable $e) {
            $nextAttempts = $delivery->attempts + 1;
            $backoffSecs = min(pow(2, $nextAttempts), 24 * 60 * 60);
            $nextAttemptAt = (new \DateTime())->modify("+{$backoffSecs} seconds");
            $nextStatus = $nextAttempts >= 5 ? 'Failed' : 'Pending';

            echo "Webhook delivery {$delivery->id} failed: " . $e->getMessage() . "\n";

            try {
                $tenantId = $subscription ? $subscription->tenant_id : 'default-tenant';
                (new \InventoryApp\Application\Notification\Services\NotificationService())->createNotification(
                    $tenantId,
                    "Webhook Delivery Failed",
                    json_encode([
                        'id'           => $delivery->id,
                        'targetUrl'    => $subscription ? $subscription->target_url : 'unknown',
                        'eventType'    => $delivery->event_type,
                        'payload'      => $delivery->payload,
                        'errorMessage' => $e->getMessage(),
                        'attemptCount' => $nextAttempts
                    ]),
                    'webhook_failed'
                );
            } catch (\Throwable $notiEx) {
                error_log('Failed to create webhook_failed notification: ' . $notiEx->getMessage());
            }

            $delivery->status = $nextStatus;
            $delivery->attempts = $nextAttempts;
            $delivery->last_error = $e->getMessage();
            $delivery->next_attempt_at = $nextAttemptAt;
        }
    }

} while (!$once);

echo "DDD Webhook Delivery Worker finished.\n";
