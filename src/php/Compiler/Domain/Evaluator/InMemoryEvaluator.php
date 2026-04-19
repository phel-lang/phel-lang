<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Evaluator;

use ParseError;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Run\Infrastructure\Service\DebugLineTap;
use Throwable;

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
        } catch (Throwable $throwable) {
            $headerOffset = substr_count($phpCode, "\n") - substr_count($code, "\n");
            throw EvaluatedCodeException::fromThrowableAndCompiledCode($throwable, $code, $headerOffset);
        }
    }
}
