<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

interface MetaInterface
{
    public function getMeta(): ?PersistentMapInterface;

    public function withMeta(?PersistentMapInterface $meta): static;
}
