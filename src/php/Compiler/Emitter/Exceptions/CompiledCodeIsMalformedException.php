<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\Exceptions;

use RuntimeException;
use Throwable;

final class CompiledCodeIsMalformedException extends RuntimeException
{
    public static function fromThrowable(Throwable $e): self
    {
        return new self($e->getMessage(), 0, $e);
    }
}
