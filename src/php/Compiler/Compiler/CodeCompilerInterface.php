<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Compiler\Emitter\EmitterResult;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;

interface CodeCompilerInterface
{
    public const DEFAULT_SOURCE = 'string';

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(string $phelCode, string $source = self::DEFAULT_SOURCE): EmitterResult;
}
