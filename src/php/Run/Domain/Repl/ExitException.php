<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use RuntimeException;

final class ExitException extends RuntimeException
{
    public static function fromRepl(): self
    {
        return new self('Exit from REPL!');
    }
}
