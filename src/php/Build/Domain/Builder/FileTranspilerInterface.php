<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder;

interface FileTranspilerInterface
{
    public function transpileFile(string $src, string $dest, bool $enableSourceMaps): TraspiledFile;
}
