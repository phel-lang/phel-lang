<?php

declare(strict_types=1);

namespace Phel\Compiler\Lexer\Exceptions;

use RuntimeException;

final class LexerValueException extends RuntimeException
{
    public static function unexpectedLexerState(): self
    {
        return new self('Unexpected lexer state');
    }
}
