<?php

declare(strict_types=1);

namespace Phel\Internal\Infrastructure;

use Phel\Lang\Collections\Map\PersistentMapInterface;

interface PhelFnLoaderInterface
{
    /**
     * @return array<string,PersistentMapInterface>
     */
    public function getNormalizedPhelFunctions(): array;
}
