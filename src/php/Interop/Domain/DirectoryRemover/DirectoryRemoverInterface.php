<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\DirectoryRemover;

interface DirectoryRemoverInterface
{
    public function removeDir(): void;
}
