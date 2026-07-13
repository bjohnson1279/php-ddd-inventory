<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap database capsule
$capsule = require_once __DIR__ . '/../src/Infrastructure/Persistence/bootstrap_database.php';

use Illuminate\Database\Capsule\Manager as DB;
use InventoryApp\Infrastructure\ServiceContainer;

$once = in_array('--once', $argv);
$repo = ServiceContainer::outboxRepo();

echo "Starting DDD Outbox Worker...\n";

do {
    $pending = $repo->fetchPending(10);
    if (empty($pending)) {
        if ($once) {
            echo "No outbox events found. Exiting.\n";
            break;
        }
        usleep(1000000); // 1s
        continue;
    }

    foreach ($pending as $event) {
        $id = $event->id;
        $name = $event->eventName;
        $payloadStr = $event->payload;

        $payloadData = json_decode($payloadStr, true) ?: [];
        $traceId = $payloadData['traceId'] ?? \InventoryApp\Infrastructure\Telemetry\TraceContext::generateTraceId();
        \InventoryApp\Infrastructure\Telemetry\TraceContext::setTraceId($traceId);

        echo "[Trace: {$traceId}] Processing Outbox Event ID: {$id} ({$name})...\n";

        try {
            // Reconcile and publish event to Kafka if configured
            $kafkaUrl = getenv('KAFKA_URL');
            if ($kafkaUrl && extension_loaded('rdkafka')) {
                $conf = new \RdKafka\Conf();
                $conf->set('metadata.broker.list', $kafkaUrl);
                $producer = new \RdKafka\Producer($conf);
                $topic = $producer->newTopic('inventory-events');
                
                $tenantId = function_exists('tenantId') ? tenantId() : 'system';
                
                $kafkaPayload = [
                    'type' => $name,
                    'payload' => array_merge($payloadData, [
                        'occurredAt' => $event->occurredOn->format(\DateTimeInterface::ATOM),
                        'tenantId' => $tenantId,
                        'traceId' => $traceId
                    ])
                ];

                $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($kafkaPayload), $tenantId);
                $producer->flush(500);
            } else {
                echo "[Trace: {$traceId}] [Outbox Worker] Kafka not enabled or extension missing. Mocking external publish.\n";
            }

            // Enqueue webhooks for active subscriptions matching tenant and event name
            $eventTenantId = $payloadData['tenantId'] ?? ($payloadData['tenantId']['value'] ?? 'tenant-1');
            $subscriptions = \InventoryApp\Infrastructure\Models\WebhookSubscriptionModel::where('tenant_id', $eventTenantId)
                ->where('is_active', true)
                ->get();

            foreach ($subscriptions as $sub) {
                $subEventTypes = json_decode($sub->event_types, true) ?: [];
                if (in_array($name, $subEventTypes)) {
                    \InventoryApp\Infrastructure\Models\WebhookDeliveryModel::create([
                        'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                        'tenant_id' => $eventTenantId,
                        'subscription_id' => $sub->id,
                        'event_type' => $name,
                        'payload' => $payloadStr,
                        'status' => 'Pending',
                        'attempts' => 0,
                        'next_attempt_at' => new \DateTime()
                    ]);
                }
            }

            // Mark processed
            $repo->markProcessed($id);
            echo "[Trace: {$traceId}] Outbox Event {$id} processed successfully.\n";
        } catch (\Throwable $e) {
            echo "[Trace: {$traceId}] Outbox Event {$id} failed: " . $e->getMessage() . "\n";
            $repo->markFailed($id, $e->getMessage());
        }
    }

} while (!$once);

echo "DDD Outbox Worker finished.\n";
