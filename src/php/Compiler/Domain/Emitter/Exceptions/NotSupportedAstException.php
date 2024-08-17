<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\Exceptions;

use RuntimeException;

use function sprintf;

final class NotSupportedAstException extends RuntimeException
{
    public static function withClassName(string $astNodeClassName): self
    {
        return new self(sprintf("Not supported AstClassName: '%s'", $astNodeClassName));
    }
}
