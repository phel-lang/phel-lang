<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Compiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Emitter\EmitterResult;
use Phel\Transpiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Exceptions\CompilerException;
use Phel\Transpiler\Infrastructure\CompileOptions;

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
    public function compileForm(float|bool|int|string|TypeInterface|null $form, CompileOptions $compileOptions): EmitterResult;
}
