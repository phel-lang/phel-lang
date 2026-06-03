<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

trait MetaTrait
{
    /** @var PersistentMapInterface<mixed, mixed>|null */
    private ?PersistentMapInterface $meta = null;

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * Mutates `$meta` in place and returns the same instance rather than a
     * fresh copy. Callers that need value-immutability (e.g. when the receiver
     * is interned or shared) must clone before calling, or a type using this
     * trait must override this method.
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        $this->meta = $meta;
        return $this;
    }
}
