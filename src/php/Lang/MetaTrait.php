<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;

trait MetaTrait
{
    private ?PersistentHashMapInterface $meta = null;

    public function getMeta(): PersistentHashMapInterface
    {
        if ($this->meta === null) {
            $this->meta = TypeFactory::getInstance()->emptyPersistentHashMap();
        }

        return $this->meta;
    }

    public function withMeta(?PersistentHashMapInterface $meta)
    {
        $this->meta = $meta;
        return $this;
    }
}
