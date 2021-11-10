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

    /** @var T */
    private $value;

    /**
     * @param T $value
     */
    public function __construct(?PersistentMapInterface $meta, $value)
    {
        $this->meta = $meta;
        $this->value = $value;
    }

    /**
     * @param T $value
     */
    public function set($value): void
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

    public function equals($other): bool
    {
        return $this === $other;
    }

    public function hash(): int
    {
        return crc32(spl_object_hash($this));
    }
}
