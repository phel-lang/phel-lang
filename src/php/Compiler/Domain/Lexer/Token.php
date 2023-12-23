<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer;

use Phel\Lang\SourceLocation;

final readonly class Token
{
    public const T_WHITESPACE = 2;

    public const T_NEWLINE = 3;

    public const T_COMMENT = 4;

    public const T_UNQUOTE_SPLICING = 5;

    public const T_OPEN_PARENTHESIS = 6;

    public const T_CLOSE_PARENTHESIS = 7;

    public const T_OPEN_BRACKET = 8;

    public const T_CLOSE_BRACKET = 9;

    public const T_OPEN_BRACE = 10;

    public const T_CLOSE_BRACE = 11;

    public const T_QUOTE = 12;

    public const T_UNQUOTE = 13;

    public const T_QUASIQUOTE = 14;

    public const T_CARET = 15;

    public const T_FN = 16;

    public const T_STRING = 17;

    public const T_ATOM = 18;

    public const T_EOF = 100;

    public function __construct(
        private int $type,
        private string $code,
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
    ) {
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }
}
