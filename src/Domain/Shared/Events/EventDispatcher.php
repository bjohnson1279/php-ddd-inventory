<?php

namespace InventoryApp\Domain\Shared\Events;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Simple synchronous event dispatcher.
 *
 * Implements PSR-14 EventDispatcherInterface so it can be injected into any
 * service that depends on the standard contract (e.g. InventoryService).
 *
 * Register listeners with subscribe() before dispatching:
 *
 *   $dispatcher->subscribe(StockReceived::class, [$listener, 'handle']);
 */
class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, callable[]> */
    private array $listeners = [];
    private ?\InventoryApp\Infrastructure\Messaging\KafkaMessageBroker $kafkaBroker = null;

    private function getKafkaBroker(): ?\InventoryApp\Infrastructure\Messaging\KafkaMessageBroker
    {
        if ($this->kafkaBroker === null) {
            $kafkaUrl = getenv('KAFKA_URL');
            if ($kafkaUrl) {
                $this->kafkaBroker = new \InventoryApp\Infrastructure\Messaging\KafkaMessageBroker($kafkaUrl);
            }
        }
        return $this->kafkaBroker;
    }

    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch an event to all registered listeners for its class.
     * Returns the (potentially modified) event per PSR-14.
     */
    public function dispatch(object $event): object
    {
        $eventClass = get_class($event);

        $broker = $this->getKafkaBroker();
        if ($broker) {
            $broker->publish('inventory-events', $event);
        }

        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            // Check if this listener should be executed asynchronously
            if (is_array($listener) && count($listener) === 2 && is_object($listener[0])) {
                $listenerObj = $listener[0];
                if ($listenerObj instanceof QueuedListenerInterface) {
                    $this->queueListener($listenerObj, $event);
                    continue; // Skip running synchronously
                }
            }

            call_user_func($listener, $event);
        }
        return $event;
    }

    private function queueListener(object $listenerObj, object $event): void
    {
        $tenantId = function_exists('tenantId') ? tenantId() : 'system';
        
        try {
            \Illuminate\Database\Capsule\Manager::table('queued_jobs')->insert([
                'id'             => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'tenant_id'      => $tenantId,
                'listener_class' => get_class($listenerObj),
                'event_data'     => base64_encode(serialize($event)),
                'attempts'       => 0,
                'reserved_at'    => null,
                'available_at'   => date('Y-m-d H:i:s'),
                'created_at'     => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            // Fallback: Run synchronously if DB connection is unavailable (e.g. in some in-memory unit tests)
            error_log("Failed to queue listener " . get_class($listenerObj) . ": " . $e->getMessage() . ". Running synchronously.");
            if (method_exists($listenerObj, 'handle')) {
                $listenerObj->handle($event);
            }
        }
    }
}
