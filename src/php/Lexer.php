<?php

namespace Phel;

use Exception;
use Generator;
use Phel\Lang\SourceLocation;

class Lexer
{

    /**
     * @var int
     */
    private $cursor = 0;

    /**
     * @var int
     */
    private $line = 1;

    /**
     * @var int
     */
    private $column = 1;

    /**
     * @var string[]
     */
    private $regexps = [
        "([\n \t\r]+)", // Whitespace (index: 2)
        "(\#[^\n]*)", // Comment (index: 3)
        "(,@)", // unquote-splicing (index: 4)
        "(\()", // open parenthesis (index: 5)
        "(\))", // close parenthesis (index: 6)
        "(\[)", // open bracket (index: 7)
        "(\])", // close bracket (index: 8)
        "(\{)", // open brace (index: 9)
        "(\})", // close brace (index: 10)
        "(')", // quote (index: 11)
        "(,)", // unquote (index: 12)
        "(`)", // quasiquote (index: 13)
        "(\^)", // caret (index: 14)
        "(@\[)", // array (index: 15)
        "(@\{)", // table (index: 16)
        "(\|\()", // short fn (index: 17)
        '("(?:[^"\\\\]++|\\\\.)*+")', // String (index: 18)
        "([^\(\)\[\]\{\}',`@ \n\r\t\#]+)" // Atom (index: 19)
    ];

    /**
     * @var string
     */
    private $combinedRegex;

    public function __construct()
    {
        $this->combinedRegex = "/(?:" . implode("|", $this->regexps) . ")/mA";
    }

    public function lexString(string $code, string $source = 'string'): Generator
    {
        $this->cursor = 0;
        $this->line = 1;
        $this->column = 0;
        $code = rtrim($code);
        $end = strlen($code);

        $startLocation = new SourceLocation($source, $this->line, $this->column);

        while ($this->cursor < $end) {
            if (preg_match($this->combinedRegex, $code, $matches, 0, $this->cursor)) {
                $this->moveCursor($matches[0]);
                $endLocation = new SourceLocation($source, $this->line, $this->column);

                yield new Token(count($matches), $matches[0], $startLocation, $endLocation);

                $startLocation = $endLocation;
            } else {
                throw new Exception("Unexpected state");
            }
        }

        yield new Token(Token::T_EOF, "", $startLocation, $startLocation);
    }

    private function moveCursor(string $str): void
    {
        $len = strlen($str);
        $this->cursor += $len;
        $lastNewLinePos = strrpos($str, "\n");

        if ($lastNewLinePos !== false) {
            $this->line += substr_count($str, "\n");
            $this->column = $len - $lastNewLinePos - 1;
        } else {
            $this->column += $len;
        }
    }
}
