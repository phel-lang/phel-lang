<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Compiler\Emitter\EmitterResult;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Lang\TypeInterface;

interface CodeCompilerInterface
{
    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileString(string $phelCode, CompileOptions $compileOptions): EmitterResult;

    /**
     * @param TypeInterface|string|float|int|bool|null $form The phel form to evaluate
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileForm($form, CompileOptions $compileOptions): EmitterResult;
}
