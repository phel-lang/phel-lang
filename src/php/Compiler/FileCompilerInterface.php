<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Exceptions\CompiledCodeIsMalformedException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\FileException;

interface FileCompilerInterface
{
    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(string $filename): string;
}
