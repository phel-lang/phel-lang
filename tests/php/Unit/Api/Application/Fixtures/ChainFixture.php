<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application\Fixtures;

use Stringable;

/**
 * Fluent fixture whose methods declare reflectable return types, so the
 * interop resolver can walk a `php/->` chain and a factory binding.
 */
final class ChainFixture implements Stringable
{
    public string $name = '';

    public function __toString(): string
    {
        return $this->name;
    }

    public static function make(): self
    {
        return new self();
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function next(): self
    {
        return $this;
    }

    public function size(): int
    {
        return 0;
    }

    public function orInt(): self|int
    {
        return $this->name === '' ? 0 : $this;
    }

    public function andStringable(): self&Stringable
    {
        return $this;
    }
}
