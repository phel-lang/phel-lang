<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

interface FileCompilerInterface
{
    /**
     * @param int|null $optimizationLevel `null` falls back to the implementation's configured default
     */
    public function compileFile(string $src, string $dest, bool $enableSourceMaps, ?int $optimizationLevel = null): CompiledFile;
}
