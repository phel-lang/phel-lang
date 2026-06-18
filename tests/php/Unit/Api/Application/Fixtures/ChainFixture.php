<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application\Fixtures;

/**
 * Fluent fixture whose methods declare reflectable return types, so the
 * interop resolver can walk a `php/->` chain and a factory binding.
 */
final class ChainFixture
{
    public string $name = '';

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
}
