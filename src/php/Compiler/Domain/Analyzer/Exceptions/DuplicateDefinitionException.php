<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use Phel\Lang\Symbol;
use RuntimeException;

use function sprintf;

final class DuplicateDefinitionException extends RuntimeException
{
    public static function forSymbol(string $namespace, Symbol $name): self
    {
        throw new RuntimeException(sprintf(
            'Symbol %s is already bound in namespace %s',
            $name->getName(),
            $namespace,
        ));
    }
}
