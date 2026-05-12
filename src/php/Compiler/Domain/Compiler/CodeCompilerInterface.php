<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Compiler;

use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Lang\TypeInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\FileException;

interface CodeCompilerInterface
{
    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileString(string $phelCode, CompileOptions $compileOptions): EmitterResult;

    /**
     * @param bool|float|int|string|TypeInterface|null $form The phel form to evaluate
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileForm(float|bool|int|string|TypeInterface|null $form, CompileOptions $compileOptions): EmitterResult;
}
