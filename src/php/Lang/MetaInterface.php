<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

/**
 * @template TSelf
 */
interface MetaInterface
{
    public function getMeta(): ?PersistentMapInterface;

    /**
     * @return TSelf
     */
    public function withMeta(?PersistentMapInterface $meta);
}
