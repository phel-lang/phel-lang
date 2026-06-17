<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application\Fixtures;

/**
 * A counter used to exercise reflection-backed hover for properties,
 * constants, and the class itself.
 */
final class HoverFixture implements HoverContract
{
    /** The largest value the counter accepts. */
    public const int MAX = 10;

    /** The current count. */
    public int $count = 0;

    public function __construct(public readonly string $label) {}

    /**
     * Increments the counter and returns the new value.
     *
     * @param int $by the amount to add
     */
    public function increment(int $by): int
    {
        return $this->count += $by;
    }
}
