<?php

declare(strict_types=1);

namespace Phel\Compiler\Application\Port;

use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\ValueObject\CompileOptions;
use Phel\Lang\TypeInterface;

/**
 * Driving port (primary port) for evaluating Phel code.
 */
interface EvaluateCodeUseCase
{
    /**
     * Evaluates Phel source code and returns the result.
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalString(string $phelCode, CompileOptions $compileOptions): mixed;

    /**
     * Evaluates a Phel form (AST) and returns the result.
     *
     * @param bool|float|int|string|TypeInterface|null $form The phel form to evaluate
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalForm(float|bool|int|string|TypeInterface|null $form, CompileOptions $compileOptions): mixed;
}
