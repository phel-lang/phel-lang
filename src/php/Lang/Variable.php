<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

/**
 * @template T
 */
final class Variable extends AbstractType
{
    use MetaTrait;

    /**
     * @param T $value
     */
    public function __construct(
        ?PersistentMapInterface $meta,
        private mixed $value,
    ) {
        $this->meta = $meta;
    }

    /**
     * @param T $value
     */
    public function set(mixed $value): void
    {
        $this->value = $value;
    }

    /**
     * @return T
     */
    public function deref()
    {
        return $this->value;
    }

    public function equals(mixed $other): bool
    {
        return $this === $other;
    }

    public function hash(): int
    {
        return crc32(spl_object_hash($this));
    }
}
