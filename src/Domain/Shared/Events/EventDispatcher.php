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
        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            call_user_func($listener, $event);
        }
        return $event;
    }
}
