<?php

declare(strict_types=1);

namespace Phel\Interop\FileCreator;

use Phel\Interop\ReadModel\Wrapper;

interface FileCreatorInterface
{
    public function createFromWrapper(Wrapper $wrapper): void;
}
