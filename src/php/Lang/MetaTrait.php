<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;

trait MetaTrait
{
    private ?PersistentHashMapInterface $meta = null;

    public function getMeta(): ?PersistentHashMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentHashMapInterface $meta)
    {
        $this->meta = $meta;
        return $this;
    }
}
