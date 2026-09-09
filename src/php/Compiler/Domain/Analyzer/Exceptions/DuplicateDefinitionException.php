<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;

use function sprintf;

/**
 * @internal
 */
final class DuplicateDefinitionException extends AbstractLocatedException
{
    public static function forSymbol(
        string $namespace,
        Symbol $name,
        ?SourceLocation $firstDefinitionLocation = null,
    ): self {
        $e = new self(
            sprintf("Symbol '%s' is already bound in namespace '%s'", $name->getName(), $namespace),
            $name->getStartLocation(),
            $name->getEndLocation(),
        );
        $e->setErrorCode(ErrorCode::DUPLICATE_DEFINITION);

        if ($firstDefinitionLocation instanceof SourceLocation) {
            $e->setRelatedLocationNote(sprintf(
                'first defined at %s:%d',
                $firstDefinitionLocation->getFile(),
                $firstDefinitionLocation->getLine(),
            ));
        }

        return $e;
    }
}
