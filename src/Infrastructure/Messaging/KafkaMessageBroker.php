<?php

namespace InventoryApp\Infrastructure\Messaging;

class KafkaMessageBroker
{
    private string $brokerUrl;

    public function __construct(string $brokerUrl)
    {
        $this->brokerUrl = $brokerUrl;
    }

    public function publish(string $topicName, object $event): void
    {
        if (!extension_loaded('rdkafka')) {
            // Quietly fallback for unit test and local non-docker environments
            error_log("[KafkaMessageBroker] rdkafka extension not loaded. Skipping Kafka publish.");
            return;
        }

        try {
            $conf = new \RdKafka\Conf();
            $conf->set('metadata.broker.list', $this->brokerUrl);

            $producer = new \RdKafka\Producer($conf);
            $topic = $producer->newTopic($topicName);

            $occurredAt = method_exists($event, 'occurredOn')
                ? $event->occurredOn()->format(\DateTimeInterface::ATOM)
                : date('c');

            $tenantId = function_exists('tenantId') ? tenantId() : 'system';

            // Get simple name of event class (e.g. StockReceived)
            $classParts = explode('\\', get_class($event));
            $eventName = end($classParts);

            // Dynamically serialize public properties
            $properties = [];
            $reflect = new \ReflectionClass($event);
            foreach ($reflect->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                $val = $prop->getValue($event);
                // Object value object stringification support (like Sku, LocationId)
                if (is_object($val) && method_exists($val, '__toString')) {
                    $properties[$prop->getName()] = (string)$val;
                } else if (is_object($val) && method_exists($val, 'value')) {
                    $properties[$prop->getName()] = $val->value();
                } else {
                    $properties[$prop->getName()] = $val;
                }
            }

            $payload = [
                'type' => $eventName,
                'payload' => array_merge($properties, [
                    'occurredAt' => $occurredAt,
                    'tenantId' => $tenantId
                ])
            ];

            $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($payload), $tenantId);
            $producer->flush(500); // Flush events with 500ms block timeout
            error_log("[KafkaMessageBroker] Successfully published event '{$eventName}' to Kafka topic '{$topicName}'");
        } catch (\Throwable $e) {
            error_log("[KafkaMessageBroker] Failed to publish event: " . $e->getMessage());
        }
    }
}
