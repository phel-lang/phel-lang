<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder\Output;

interface EntryPointPhpFileInterface
{
    public function createFile(): void;
}
