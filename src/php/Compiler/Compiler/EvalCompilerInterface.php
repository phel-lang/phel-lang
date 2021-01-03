<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;

interface EvalCompilerInterface
{
    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompilerException|ReaderException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $code);
}
