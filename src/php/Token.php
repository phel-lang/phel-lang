<?php 

namespace Phel;

use Phel\Lang\SourceLocationTrait;
use Phel\Stream\SourceLocation;

class Token {

    use SourceLocationTrait;

    
    public const T_WHITESPACE = 2;
    public const T_COMMENT = 3;
    public const T_UNQUOTE_SPLICING = 4;
    public const T_OPEN_PARENTHESIS = 5;
    public const T_CLOSE_PARENTHESIS = 6;
    public const T_OPEN_BRACKET = 7;
    public const T_CLOSE_BRACKET = 8;
    public const T_OPEN_BRACE = 9;
    public const T_CLOSE_BRACE = 10;
    public const T_QUOTE = 11;
    public const T_UNQUOTE = 12;
    public const T_QUASIQUOTE = 13;
    public const T_ARRAY = 14;
    public const T_TABLE = 15;
    public const T_STRING = 16;
    public const T_ATOM = 17;

    public const T_EOF = 100;

    /**
     * @var string
     */
    private $code;

    /**
     * @var int
     */
    private $type;

    public function __construct(int $type, string $code, SourceLocation $startLocation, SourceLocation $endLocation)
    {
        $this->type = $type;
        $this->code = $code;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }

    public function getCode(): string {
        return $this->code;
    }

    public function getType(): int {
        return $this->type;
    }
}