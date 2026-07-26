<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\DirectoryRemover;

/**
 * @internal
 */
interface DirectoryRemoverInterface
{
    public function removeDir(): void;
}
