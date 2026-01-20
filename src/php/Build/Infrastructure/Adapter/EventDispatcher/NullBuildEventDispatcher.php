<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Adapter\EventDispatcher;

use Phel\Build\Domain\Event\DomainEvent;
use Phel\Build\Domain\Port\EventDispatcher\BuildEventDispatcherPort;

/**
 * Null implementation of the event dispatcher.
 * Events are silently ignored - useful as a default when no listeners are needed.
 */
final readonly class NullBuildEventDispatcher implements BuildEventDispatcherPort
{
    public function dispatch(DomainEvent $event): void
    {
        // Intentionally empty - events are discarded
    }
}
