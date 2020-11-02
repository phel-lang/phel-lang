<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Exceptions\CompilerException;

interface EvalCompilerInterface
{
    /**
     * Evaluates a provided Phel code.
     *
     * @return mixed The result of the executed code
     *
     * @throws CompilerException
     */
    public function eval(string $code);
}
