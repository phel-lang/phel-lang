<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Adapter\EventDispatcher;

use Phel\Build\Domain\Event\DomainEvent;
use Phel\Build\Domain\Port\EventDispatcher\BuildEventDispatcherPort;

/**
 * In-memory event dispatcher that allows registering listeners.
 * Listeners are invoked synchronously when events are dispatched.
 */
final class InMemoryBuildEventDispatcher implements BuildEventDispatcherPort
{
    /** @var array<string, list<callable(DomainEvent): void>> */
    private array $listeners = [];

    /**
     * Registers a listener for a specific event type.
     *
     * @param class-string<DomainEvent>   $eventClass
     * @param callable(DomainEvent): void $listener
     */
    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(DomainEvent $event): void
    {
        $eventClass = $event::class;

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            $listener($event);
        }
    }

    /**
     * Clears all registered listeners.
     */
    public function clearListeners(): void
    {
        $this->listeners = [];
    }
}
