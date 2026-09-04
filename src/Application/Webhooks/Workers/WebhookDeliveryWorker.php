<?php

namespace InventoryApp\Application\Webhooks\Workers;

use InventoryApp\Infrastructure\Models\WebhookSubscriptionModel;
use InventoryApp\Infrastructure\Models\WebhookDeliveryModel;

class WebhookDeliveryWorker
{
    private array $dnsCache = [];

    public function run(bool $once = false): void
    {
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

            $subscriptionIds = $deliveries->pluck('subscription_id')->unique()->toArray();
            $subscriptions = WebhookSubscriptionModel::whereIn('id', $subscriptionIds)->get()->keyBy('id');

            $mh = curl_multi_init();
            $curlHandles = [];
            $deliveryExceptions = [];

            foreach ($deliveries as $delivery) {
                $delivery->status = 'Processing';
                $delivery->syncOriginal();
                echo "Processing Webhook Delivery ID: {$delivery->id}...\n";

                try {
                    $subscription = $subscriptions->get($delivery->subscription_id);
                    if (!$subscription || !$subscription->is_active) {
                        throw new \Exception("Subscription not found or inactive: {$delivery->subscription_id}");
                    }

                    // Calculate HMAC-SHA256 signature
                    $signature = hash_hmac('sha256', $delivery->payload, $subscription->secret);

                    // Prevent SSRF: Resolve IP and validate against private ranges
                    $parsedUrl = parse_url($subscription->target_url);
                    if (!$parsedUrl || !isset($parsedUrl['host'])) {
                        throw new \Exception("Invalid target URL for webhook: {$subscription->target_url}");
                    }

                    $host = $parsedUrl['host'];
                    // Default to 80 for http and 443 for https if port is not specified
                    $port = $parsedUrl['port'] ?? (isset($parsedUrl['scheme']) && strtolower($parsedUrl['scheme']) === 'https' ? 443 : 80);

                    // Resolve the hostname to an IP address
                    if (isset($this->dnsCache[$host])) {
                        $ip = $this->dnsCache[$host];
                    } else {
                        $ip = gethostbyname($host);
                        $this->dnsCache[$host] = $ip;
                    }
                    // gethostbyname returns the original string if it's already an IP or if resolution fails.
                    // So we validate if it's an IP, and if not, resolution failed.
                    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                        throw new \Exception("DNS resolution failed for webhook target host: {$host}");
                    }

                    // Validate IP address is not private or reserved (SSRF protection)
                    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        throw new \Exception("Webhook target IP is in a private or reserved range, delivery aborted: {$ip}");
                    }

                    // Execute POST request via curl
                    $ch = curl_init($subscription->target_url);
                    // Pin the connection to the validated IP to prevent DNS rebinding attacks
                    curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$ip}"]);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $delivery->payload);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'X-Webhook-Signature-256: ' . $signature,
                        'X-Webhook-Event: ' . $delivery->event_type
                    ]);

                    curl_multi_add_handle($mh, $ch);
                    $curlHandles[$delivery->id] = ['ch' => $ch, 'delivery' => $delivery, 'subscription' => $subscription];
                } catch (\Throwable $e) {
                    $deliveryExceptions[$delivery->id] = $e;
                }
            }

            // Execute concurrent requests
            if (!empty($curlHandles)) {
                do {
                    $status = curl_multi_exec($mh, $active);
                    if ($active) {
                        curl_multi_select($mh);
                    }
                } while ($active && $status == CURLM_OK);
            }

            foreach ($deliveries as $delivery) {
                if (isset($deliveryExceptions[$delivery->id])) {
                    $e = $deliveryExceptions[$delivery->id];
                    $this->handleFailure($delivery, $e, $subscriptions->get($delivery->subscription_id));
                    continue;
                }

                if (!isset($curlHandles[$delivery->id])) {
                    continue; // Should not happen
                }

                $handleData = $curlHandles[$delivery->id];
                $ch = $handleData['ch'];
                $subscription = $handleData['subscription'];

                $response = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                try {
                    if ($curlError) {
                        throw new \Exception("cURL Error: " . $curlError);
                    }

                    if ($httpCode < 200 || $httpCode >= 300) {
                        throw new \Exception("HTTP Error: " . $httpCode . " Response: " . $response);
                    }

                    // Mark as Success
                    $delivery->status = 'Success';
                    $delivery->attempts = $delivery->attempts + 1;
                    $delivery->processed_at = new \DateTime();
                    $delivery->save();

                    echo "Webhook delivery {$delivery->id} sent successfully.\n";
                } catch (\Throwable $e) {
                    $this->handleFailure($delivery, $e, $subscription);
                }
            }

            curl_multi_close($mh);

        } while (!$once);

        echo "DDD Webhook Delivery Worker finished.\n";
    }

    private function handleFailure($delivery, \Throwable $e, $subscription): void
    {
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
        $delivery->save();
    }
}
