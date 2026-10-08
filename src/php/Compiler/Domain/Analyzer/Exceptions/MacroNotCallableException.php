<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * The cause of a macro expansion error when the macro has no callable
 * definition. A source analysis never evaluates a `defmacro`, so there it
 * means the macro could not be expanded, not that the call is wrong.
 */
final class MacroNotCallableException extends RuntimeException
{
    public static function forMacro(string $namespace, string $name): self
    {
        return new self(sprintf('Macro "%s::%s" is not callable.', $namespace, $name));
    }
}
