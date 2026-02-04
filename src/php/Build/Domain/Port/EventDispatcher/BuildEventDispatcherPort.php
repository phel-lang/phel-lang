<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Port\EventDispatcher;

use Phel\Build\Domain\Event\DomainEvent;

/**
 * Driven port for dispatching build domain events.
 * Allows the application to react to significant build occurrences.
 */
interface BuildEventDispatcherPort
{
    /**
     * Dispatches a domain event to all registered listeners.
     */
    public function dispatch(DomainEvent $event): void;
}
