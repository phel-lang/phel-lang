<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;
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
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function withMeta(?PersistentMapInterface $meta): static
    {
        $copy = clone $this;
        $copy->meta = $meta;

        return $copy;
    }
}
