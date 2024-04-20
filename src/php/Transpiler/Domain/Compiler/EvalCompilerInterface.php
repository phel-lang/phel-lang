<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Compiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;
use Phel\Transpiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Transpiler\Infrastructure\TranspileOptions;

interface EvalCompilerInterface
{
    /**
     * Evaluates a provided Phel code.
     *
     *@throws TranspilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalString(string $phelCode, TranspileOptions $compileOptions): mixed;

    /**
     * Evaluates a provided Phel form.
     *
     * @param TypeInterface|string|float|int|bool|null $form The phel form to evaluate
     *
     *@throws TranspilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalForm(float|bool|int|string|TypeInterface|null $form, TranspileOptions $compileOptions): mixed;
}
