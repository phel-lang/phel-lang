<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer\Exceptions;

use RuntimeException;

final class LexerValueException extends RuntimeException
{
    public static function unexpectedLexerState(string $file, int $line, int $column): self
    {
        return new self("Cannot lex string after at column {$column} in {$file}:{$line}");
    }
}
