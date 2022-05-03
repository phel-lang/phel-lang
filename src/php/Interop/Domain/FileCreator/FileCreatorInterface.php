<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\FileCreator;

use Phel\Interop\Domain\ReadModel\Wrapper;

interface FileCreatorInterface
{
    public function createFromWrapper(Wrapper $wrapper): void;
}
