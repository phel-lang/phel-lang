<?php

namespace Phel;

use Exception;
use Phel\Stream\SourceLocation;
use Phel\Token\AtomToken;
use Phel\Token\CommentToken;
use Phel\Token\EOFToken;
use Phel\Token\StringToken;
use Phel\Token\SyntaxToken;
use Phel\Token\WhitespaceToken;

class Lexer {
    
    private $cursor = 0;
    private $line = 1;
    private $column = 1;

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
        $this->combinedRegex = "/(?:" . implode("|", $this->regexps) . ")/mA";
    }

    public function lexString(string $code, $source = 'string') {
        $this->cursor = 0;
        $this->line = 1;
        $this->column = 0;
        $end = strlen($code);

        $startLocation = new SourceLocation($source, $this->line, $this->column);
        
        while ($this->cursor < $end) {
            if (preg_match($this->combinedRegex, $code, $matches, 0, $this->cursor)) {
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

                $startLocation = $endLocation;
            } else {
                throw new Exception("Unexpected state");
            }
        }

        yield new EOFToken($startLocation);
    }

    private function moveCursor($str) {
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