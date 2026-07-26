<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile\Output;

/**
 * @internal
 */
interface EntryPointPhpFileInterface
{
    public function createFile(): void;
}
