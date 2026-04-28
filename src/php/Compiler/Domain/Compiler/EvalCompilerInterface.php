<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Compiler;

use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Infrastructure\CompileOptions;

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
     * Phel forms (literals, symbols, lists, vectors, etc.) are compiled and
     * executed. Any other PHP value (e.g. a Closure or arbitrary object) is
     * already evaluated and is returned as-is, matching Clojure's
     * self-evaluating semantics.
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalForm(mixed $form, CompileOptions $compileOptions): mixed;
}
