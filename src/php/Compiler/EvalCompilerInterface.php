<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;

interface EvalCompilerInterface
{
    /**
     * Evaluates a provided Phel code.
     *
     * @return mixed The result of the executed code
     *
     * @throws CompilerException|ReaderException
     */
    public function eval(string $code);
}
