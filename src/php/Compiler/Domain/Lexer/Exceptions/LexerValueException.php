<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer\Exceptions;

use RuntimeException;

use function sprintf;

final class LexerValueException extends RuntimeException
{
    public static function unexpectedLexerState(string $file, int $line, int $column): self
    {
        return new self(sprintf('Cannot lex string after at column %d in %s:%d', $column, $file, $line));
    }
}
