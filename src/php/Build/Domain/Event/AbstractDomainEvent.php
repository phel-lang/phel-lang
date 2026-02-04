<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Event;

use DateTimeImmutable;

/**
 * Base implementation for domain events with common functionality.
 */
abstract readonly class AbstractDomainEvent implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        $parts = explode('\\', static::class);

        return end($parts);
    }
}
