<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\Exceptions;

use RuntimeException;

use function sprintf;

final class NotSupportedAstException extends RuntimeException
{
    public static function withClassName(string $astNodeClassName): self
    {
        return new self(sprintf(
            "No node emitter is registered for AST node '%s'. "
            . 'The analyzer produced a node the emitter cannot handle; '
            . 'register an emitter for it in NodeEmitterFactory::instantiateEmitter().',
            $astNodeClassName,
        ));
    }
}
