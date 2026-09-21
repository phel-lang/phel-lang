<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;
use Phel\Lang\Collections\Map\PersistentMapInterface;

interface MetaInterface
{
    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface;

    /**
     * Returns a copy carrying `$meta`. The receiver is unchanged.
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function withMeta(?PersistentMapInterface $meta): static;
}
