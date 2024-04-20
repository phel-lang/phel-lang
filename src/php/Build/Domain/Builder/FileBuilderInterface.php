<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder;

interface FileBuilderInterface
{
    public function compileFile(string $src, string $dest, bool $enableSourceMaps): TraspiledFile;
}
