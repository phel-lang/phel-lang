<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use ParseError;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Run\Infrastructure\Service\DebugLineTap;

/**
 * Evaluates compiled PHP code in-memory using eval().
 * Avoids temp file I/O overhead — ideal for REPL and interactive use.
 */
final class InMemoryEvaluator implements EvaluatorInterface
{
    public function eval(string $code): mixed
    {
        $phpCode = DebugLineTap::isEnabled()
            ? "declare(ticks=1);\n" . $code
            : $code;

        try {
            return eval($phpCode);
        } catch (ParseError $parseError) {
            throw CompiledCodeIsMalformedException::fromThrowable($parseError);
        }
    }
}
