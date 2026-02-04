<?php

declare(strict_types=1);

namespace Phel\Compiler\Application\Port;

use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\ValueObject\CompileOptions;
use Phel\Lang\TypeInterface;

/**
 * Driving port (primary port) for compiling Phel code to PHP.
 */
interface CompileCodeUseCase
{
    /**
     * Compiles Phel source code string to PHP code.
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileString(string $phelCode, CompileOptions $compileOptions): EmitterResult;

    /**
     * Compiles a Phel form (AST) to PHP code.
     *
     * @param bool|float|int|string|TypeInterface|null $form The phel form to compile
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileForm(float|bool|int|string|TypeInterface|null $form, CompileOptions $compileOptions): EmitterResult;
}
