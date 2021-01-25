<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Exceptions\CompilerException;
use Phel\Exceptions\Parser\UnfinishedParserException;

interface EvalCompilerInterface
{
    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $code);
}
