<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use RuntimeException;

use function sprintf;

final class DuplicateDefinitionException extends RuntimeException
{
    public static function forSymbol(string $namespace, Symbol $name): self
    {
        $location = $name->getStartLocation();
        $fileAndLine = '';

        if ($location instanceof SourceLocation) {
            $fileAndLine = sprintf(' in %s:%d', $location->getFile(), $location->getLine());
        }

        return new self(sprintf(
            'Symbol %s is already bound in namespace %s%s',
            $name->getName(),
            $namespace,
            $fileAndLine,
        ));
    }
}
