<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Fixture;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use RuntimeException;

/**
 * An associative target whose `put` records what
 * {@see LifetimeProbe::alive()} counted when it ran.
 *
 * @implements TransientMapInterface<mixed, mixed>
 */
final class ProbeObservingMap implements TransientMapInterface
{
    public ?int $aliveAtPut = null;

    public function put(mixed $key, mixed $value): self
    {
        $this->aliveAtPut = LifetimeProbe::alive();

        return $this;
    }

    public function remove(mixed $key): self
    {
        return $this;
    }

    public function find(mixed $key): mixed
    {
        return null;
    }

    public function persistent(): PersistentMapInterface
    {
        throw new RuntimeException('Not supported');
    }

    public function contains(mixed $key): bool
    {
        return false;
    }

    public function count(): int
    {
        return 0;
    }

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->put($offset, $value);
    }

    public function offsetUnset(mixed $offset): void {}
}
