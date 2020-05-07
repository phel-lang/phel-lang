<?php

namespace Phel;

use Exception;
use Phel\Exceptions\ReaderException;
use Phel\Stream\CharData;
use Phel\Stream\CharStream;
use Phel\Stream\SourceLocation;
use Phel\Token\AtomToken;
use Phel\Token\CommentToken;
use Phel\Token\EOFToken;
use Phel\Token\StringToken;
use Phel\Token\SyntaxToken;
use Phel\Token\WhitespaceToken;

class Lexer {
    
    private $code = "";
    private $cursor = 0;
    private $line = 1;
    private $column = 1;
    private $end = 0;

    private $regexps = [
        "([\n \t\r]+)", // Whitespace
        "(\#[^\n]*)", // Comment
        "(,@)", // Two char symbol
        "([\(\)\[\]\{\}',`@])", // Single char symbol
        "((?:\"(?:\\\\\"|[^\"])*\"))", // String
        "([^\(\)\[\]\{\}',`@ \n\r\t\#]+)" // Atom
    ];

    private $combinedRegex;

    public function __construct()
    {
        $this->combinedRegex = "/(?:" . implode("|", $this->regexps) . ")/A";
    }

    public function lexString(string $code, $source = 'string') {
        $this->code = $code;
        $this->cursor = 0;
        $this->line = 1;
        $this->column = 0;
        $this->end = strlen($code);

        while ($this->cursor < $this->end) {
            $startLocation = new SourceLocation($source, $this->line, $this->column);

            if (preg_match($this->combinedRegex, $this->code, $matches, 0, $this->cursor)) {
                $this->moveCursor($matches[0]);
                $endLocation = new SourceLocation($source, $this->line, $this->column);

                switch (count($matches)) {
                    case 2: // Whitespace
                        yield new WhitespaceToken($matches[0], $startLocation, $endLocation);
                        break;

                    case 3: // Comment
                        yield new CommentToken($matches[0], $startLocation, $endLocation);
                        break;

                    case 4: // Two char Symbol
                        yield new SyntaxToken($matches[0], $startLocation, $endLocation);
                        break;

                    case 5: // Single char symbol
                        yield new SyntaxToken($matches[0], $startLocation, $endLocation);
                        break;

                    case 6: // String
                        yield new StringToken($matches[0], $startLocation, $endLocation);
                        break;

                    case 7: // Atom
                        yield new AtomToken($matches[0], $startLocation, $endLocation);
                        break;

                    default:
                        throw new Exception("Unexpected match state: " . count($matches) . " " . $matches[0]);
                }
            } else {
                throw new Exception("Unexpected state");
            }
        }

        yield new EOFToken(new SourceLocation($source, $this->line, $this->column));
    }

    private function moveCursor($str) {
        $len = strlen($str);
        $this->cursor += $len;
        $this->line += substr_count($str, "\n");
        $lastNewLinePos = strrpos($str, "\n");

        if ($lastNewLinePos !== false) {
            $this->column = $len - $lastNewLinePos - 1;
        } else {
            $this->column += $len;
        }
    }
}