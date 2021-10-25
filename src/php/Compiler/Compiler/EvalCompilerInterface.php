<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Compiler\Exceptions\CompilerException;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;

interface EvalCompilerInterface
{
    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $phelCode, CompileOptions $compileOptions);
}
