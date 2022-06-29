<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Compiler;

use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Infrastructure\CompileOptions;
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
    public function compileForm(float|bool|int|string|TypeInterface|null $form, CompileOptions $compileOptions): EmitterResult;
}
