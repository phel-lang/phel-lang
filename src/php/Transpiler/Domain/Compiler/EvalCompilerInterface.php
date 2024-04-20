<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Compiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Exceptions\CompilerException;
use Phel\Transpiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Transpiler\Infrastructure\CompileOptions;

interface EvalCompilerInterface
{
    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalString(string $phelCode, CompileOptions $compileOptions): mixed;

    /**
     * Evaluates a provided Phel form.
     *
     * @param TypeInterface|string|float|int|bool|null $form The phel form to evaluate
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalForm(float|bool|int|string|TypeInterface|null $form, CompileOptions $compileOptions): mixed;
}
