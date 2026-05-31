<?php

namespace InventoryApp\Domain\Shared\Events;

/**
 * Marker interface to designate that an event listener's handle method
 * should be executed asynchronously via the background database queue worker.
 */
interface QueuedListenerInterface
{
}
