<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;

/**
 * @template TSelf
 */
interface MetaInterface
{
    public function getMeta(): ?PersistentHashMapInterface;

    /**
     * @return TSelf
     */
    public function withMeta(?PersistentHashMapInterface $meta);
}
