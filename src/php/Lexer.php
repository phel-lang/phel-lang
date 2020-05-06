<?php

namespace Phel;

use Phel\Stream\CharData;
use Phel\Stream\CharStream;
use Phel\Token\AtomToken;
use Phel\Token\CommentToken;
use Phel\Token\EOFToken;
use Phel\Token\StringToken;
use Phel\Token\SyntaxToken;
use Phel\Token\WhitespaceToken;

class Lexer {

    private $syntaxChars = [
        '(', ')', '[', ']', '{', '}', '\'', ',', '`', '@'
    ];

    private $whitespaceChars = [" ", "\n", "\t", "\r"];

    private $breakChars;

    private $lastLocation;

    public function __construct()
    {
        $this->breakChars = array_merge($this->syntaxChars, $this->whitespaceChars, ['#']);
    }

    public function lex(CharStream $stream) {
        while ($charData = $this->readNext($stream)) {
            $char = $charData->getChar();

            // Read whitespace
            if (in_array($char, $this->whitespaceChars)) {
                yield $this->readWhitespace($stream, $charData);
                continue;
            }

            // Read comment
            if ($char === "#") {
                yield $this->readComment($stream, $charData);
                continue;
            }

            // Read string
            if ($char === "\"") {
                yield $this->readString($stream, $charData);
                continue;
            }

            // Read syntax token
            if (in_array($char, $this->syntaxChars)) {
                if ($char == ",") {
                    $followingChar = $stream->peek();
                    if ($followingChar && $followingChar->getChar() == '@') {
                        $this->readNext($stream);
                        yield new SyntaxToken(",@", $charData->getLocation(), $followingChar->getLocation());
                    } else {
                        yield new SyntaxToken($char, $charData->getLocation(), $charData->getLocation());
                    }
                } else {
                    yield new SyntaxToken($char, $charData->getLocation(), $charData->getLocation());
                }
                continue;
            }

            // Read atom
            yield $this->readAtom($stream, $charData);
        }

        yield new EOFToken($this->lastLocation);
    }

    protected function readAtom(CharStream $stream, $charData) {
        $buf = $charData->getChar();
        $beginLocation = $charData->getLocation();
        $endLocation = $charData->getLocation();

        while (true) {
            $followingChar = $stream->peek();
            if (!$followingChar || in_array($followingChar->getChar(), $this->breakChars)) {
                return new AtomToken($buf, $beginLocation, $endLocation);
                break;
            } else {
                $nextCharData = $this->readNext($stream);
                $buf .= $nextCharData->getChar();
                $endLocation = $nextCharData->getLocation();
            }
        }
    }

    protected function readWhitespace(CharStream $stream, CharData $charData) {
        $buf = $charData->getChar();
        $startLocation = $charData->getLocation();
        $endLocation = $startLocation;
        while (true) {
            $followingChar = $stream->peek();
            if (!$followingChar || !in_array($followingChar->getChar(), $this->whitespaceChars)) {
                return new WhitespaceToken($buf, $startLocation, $endLocation);
                break;
            } else {
                $next = $this->readNext($stream);
                $buf .= $next->getChar();
                $endLocation = $next->getLocation();
            }
        }
    }

    protected function readComment(CharStream $stream, CharData $charData) {
        $buf = "#";
        $startLocation = $charData->getLocation();
        $endLocation = $startLocation;
        while (true) {
            $followingChar = $stream->peek();
            if (!$followingChar || $followingChar->getChar() == "\n") {
                return new CommentToken($buf, $startLocation, $endLocation);
            } else {
                $next = $this->readNext($stream);
                $buf .= $next->getChar();
                $endLocation = $next->getLocation();
            }
        }
    }

    protected function readString(CharStream $stream, CharData $charData) {
        $buf = "\"";
        $startLocation = $charData->getLocation();
        $endLocation = $startLocation;
        $esc = false;

        while (true) {
            $followingCharData = $stream->peek();
            if (!$followingCharData) {
                throw new \Exception('missing delimiter');
            } else {
                $this->readNext($stream);
                $followingChar = $followingCharData->getChar();
                $endLocation = $followingCharData->getLocation();
                $buf .= $followingChar;

                if ($esc) {
                    $esc = false;
                } else if ($followingChar == "\\") {
                    $esc = true;
                } else if ($followingChar == "\"") {
                    return new StringToken($buf, $startLocation, $endLocation);
                }
            }
        }
    }

    private function readNext($stream) {
        $result = $stream->read();

        if ($result) {
            $this->lastLocation = $result->getLocation();
        }

        return $result;
    }
}