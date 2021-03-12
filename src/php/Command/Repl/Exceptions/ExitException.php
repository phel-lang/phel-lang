<?php

declare(strict_types=1);

namespace Phel\Command\Repl\Exceptions;

use RuntimeException;

final class ExitException extends RuntimeException
{
    public static function fromRepl(): self
    {
        return new self('Exit from REPL!');
    }
}
