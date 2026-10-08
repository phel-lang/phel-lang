<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * The cause of a macro expansion error that only a source analysis leaves
 * behind: the analysis reads a definition without evaluating it, so a macro
 * it defined has no fn yet, and a macro that runs the caller's code meets a
 * fn that is still `null`. Neither says whether the source is right.
 *
 * @internal
 */
final class UnevaluatedDefinitionException extends RuntimeException
{
    public static function macroNotCallable(string $namespace, string $name): self
    {
        return new self(sprintf('Macro "%s::%s" is not callable.', $namespace, $name));
    }

    public static function calledDuringExpansion(Throwable $cause): self
    {
        return new self($cause->getMessage(), 0, $cause);
    }
}
