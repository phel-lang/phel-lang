<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

interface FileCompilerInterface
{
    public function compileFile(string $src, string $dest, bool $enableSourceMaps): CompiledFile;
}
