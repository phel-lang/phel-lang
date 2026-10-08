<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * The cause of a macro expansion error when the macro's fn was analysed but
 * never evaluated, which only a source analysis leaves behind: the call could
 * not be expanded, which says nothing about whether it is right.
 *
 * @internal
 */
final class MacroNotCallableException extends RuntimeException
{
    public static function forMacro(string $namespace, string $name): self
    {
        return new self(sprintf('Macro "%s::%s" is not callable.', $namespace, $name));
    }
}
