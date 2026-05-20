<?php

namespace Tests\Unit\Domain\Shared\Events;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Shared\Events\EventDispatcher;
use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

class EventDispatcherTest extends TestCase
{
    private $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testSubscribeAndDispatch(): void
    {
        $event = new class implements DomainEvent {
            public function occurredOn(): DateTimeImmutable { return new DateTimeImmutable(); }
        };
        $eventClass = get_class($event);

        $called = false;
        $this->dispatcher->subscribe($eventClass, function($e) use (&$called, $event) {
            $called = true;
            $this->assertSame($event, $e);
        });

        $this->dispatcher->dispatch($event);
        $this->assertTrue($called);
    }

    public function testMultipleListenersAreCalled(): void
    {
        $event = new class implements DomainEvent {
            public function occurredOn(): DateTimeImmutable { return new DateTimeImmutable(); }
        };
        $eventClass = get_class($event);

        $count = 0;
        $this->dispatcher->subscribe($eventClass, function() use (&$count) { $count++; });
        $this->dispatcher->subscribe($eventClass, function() use (&$count) { $count++; });

        $this->dispatcher->dispatch($event);
        $this->assertEquals(2, $count);
    }

    public function testOnlyListenersForTargetEventAreCalled(): void
    {
        $eventA = new class implements DomainEvent {
            public function occurredOn(): DateTimeImmutable { return new DateTimeImmutable(); }
        };
        $eventB = new class implements DomainEvent {
            public function occurredOn(): DateTimeImmutable { return new DateTimeImmutable(); }
        };

        $calledA = false;
        $calledB = false;

        $this->dispatcher->subscribe(get_class($eventA), function() use (&$calledA) { $calledA = true; });
        $this->dispatcher->subscribe(get_class($eventB), function() use (&$calledB) { $calledB = true; });

        $this->dispatcher->dispatch($eventA);

        $this->assertTrue($calledA);
        $this->assertFalse($calledB);
    }

    public function testDispatchWithNoListenersDoesNothing(): void
    {
        $event = new class implements DomainEvent {
            public function occurredOn(): DateTimeImmutable { return new DateTimeImmutable(); }
        };

        $this->dispatcher->dispatch($event);
        $this->assertTrue(true); // Should not throw exception
    }
}
