<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Compiler\Exceptions\CompilerException;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;
use Phel\Lang\TypeInterface;

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
    public function evalForm($form, CompileOptions $compileOptions): mixed;
}
