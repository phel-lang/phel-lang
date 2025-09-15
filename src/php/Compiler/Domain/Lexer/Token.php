<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer;

use Phel\Lang\SourceLocation;

final readonly class Token
{
    public const int T_WHITESPACE = 2;

    public const int T_NEWLINE = 3;

    public const int T_COMMENT_MACRO = 4;

    public const int T_COMMENT = 5;

    public const int T_HASH_OPEN_BRACE = 6;

    public const int T_UNQUOTE_SPLICING = 7;

    public const int T_OPEN_PARENTHESIS = 8;

    public const int T_CLOSE_PARENTHESIS = 9;

    public const int T_OPEN_BRACKET = 10;

    public const int T_CLOSE_BRACKET = 11;

    public const int T_OPEN_BRACE = 12;

    public const int T_CLOSE_BRACE = 13;

    public const int T_QUOTE = 14;

    public const int T_UNQUOTE = 15;

    public const int T_QUASIQUOTE = 16;

    public const int T_CARET = 17;

    public const int T_FN = 18;

    public const int T_STRING = 19;

    public const int T_ATOM = 20;

    public const int T_EOF = 100;

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
