<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Event;

use DateTimeImmutable;

/**
 * Base interface for all domain events.
 * Domain events represent something significant that happened in the domain.
 */
interface DomainEvent
{
    /**
     * Returns when the event occurred.
     */
    public function occurredAt(): DateTimeImmutable;

    /**
     * Returns the event name for identification.
     */
    public function eventName(): string;
}
