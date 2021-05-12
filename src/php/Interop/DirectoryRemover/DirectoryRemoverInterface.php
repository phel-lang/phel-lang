<?php

declare(strict_types=1);

namespace Phel\Interop\DirectoryRemover;

interface DirectoryRemoverInterface
{
    public function removeDir(): void;
}
