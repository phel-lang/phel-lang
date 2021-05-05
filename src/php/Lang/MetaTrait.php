<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

trait MetaTrait
{
    private ?PersistentMapInterface $meta = null;

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta)
    {
        $this->meta = $meta;
        return $this;
    }
}
