<?php

namespace Phel;

use Exception;
use Generator;
use Phel\Exceptions\ReaderException;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\Stream\CodeSnippet;
use Phel\Token\AtomToken;
use Phel\Token\CommentToken;
use Phel\Token\EOFToken;
use Phel\Token\StringToken;
use Phel\Token\SyntaxToken;
use Phel\Token\WhitespaceToken;

class Reader {

    private $stringReplacements = [
        '\\' => '\\',
        '$'  =>  '$',
        'n'  => "\n",
        'r'  => "\r",
        't'  => "\t",
        'f'  => "\f",
        'v'  => "\v",
        'e'  => "\x1B",
    ];

    private $readTokens = [];

    public function readNext(Generator $tokenStream) {
        if (!$tokenStream->valid()) {
            return false;
        }

        if ($tokenStream->current() instanceof EOFToken) {
            return false;
        }

        $this->readTokens = [];
        $ast = $this->readExpression($tokenStream);

        return new ReaderResult(
            $ast,
            $this->getCodeSnippet($this->readTokens)
        );
    }

    public function readExpression(Generator $tokenStream) {
        while ($tokenStream->valid()) {
            $token = $tokenStream->current();
            $this->readTokens[] = $token;

            if ($token instanceof WhitespaceToken) {
                $tokenStream->next();
                continue;
            }

            if ($token instanceof CommentToken) {
                $tokenStream->next();
                continue;
            }

            if ($token instanceof AtomToken) {
                $tokenStream->next();
                $result = $this->parseAtom($token);

                if ($result instanceof Phel) {
                    $result->setStartLocation($token->getStartLocation());
                    $result->setEndLocation($token->getEndLocation());
                }
                return $result;
            }

            if ($token instanceof StringToken) {
                $tokenStream->next();
                return $this->parseEscapedString($token->getTrimedContent());
            }

            if ($token instanceof SyntaxToken) {
                switch ($token->getCode()) {
                    case "(":
                        return $this->readList($tokenStream, ")");
                    case "[":
                        return $this->readList($tokenStream, "]", [], true);
                    case ')':
                    case ']':
                        throw $this->buildReaderException('Unterminated list');

                    case '\'':
                        return $this->readWrap($tokenStream, "quote");
                    case ',':
                        return $this->readWrap($tokenStream, "unquote");
                    case ',@':
                        return $this->readWrap($tokenStream, "unquote-splicing");
                    case '`':
                        return $this->readQuasiquote($tokenStream);

                    case '@':
                        return $this->readDatastructure($tokenStream);

                    default:
                        throw $this->buildReaderException("Unhandled syntax token: " . $token->getCode());
                }
            }

            throw $this->buildReaderException("Unhandled token: " . print_r($token, true));
        }

        throw new Exception("EOF");
    }

    protected function readDatastructure($tokenStream) {
        $token = $tokenStream->current();
        $startLocation = $token->getStartLocation();
        $tokenStream->next();
        
        if ($tokenStream->valid()) {
            $nextToken = $tokenStream->current();
            $this->readTokens[] = $nextToken;

            if ($nextToken instanceof SyntaxToken) {
                if ($nextToken->getCode() == "[") {
                    $tuple = $this->readList($tokenStream, "]", [new Symbol("array")]);
                    $tuple->setStartLocation($startLocation);
                    return $tuple;
                } else if ($nextToken->getCode() == "{") {
                    $tuple = $this->readList($tokenStream, "}", [new Symbol("table")]);
                    if (count($tuple) % 2 == 0) {
                        throw $this->buildReaderException("Tables must have an even number of parameters");
                    }
                    $tuple->setStartLocation($startLocation);
                    return $tuple;
                }
                
                throw $this->buildReaderException("Expected [ or { after @");
            }
        }

        throw $this->buildReaderException("Expected more chars after @");
    }

    protected function readQuasiquote($tokenStream) {
        $startLocaltion = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $expression = $this->readExpressionHard($tokenStream, "missing expression");
        $q = new Quasiquote();
        $result = $q->quasiquote($expression);

        $endLocation = $tokenStream->current()->getEndLocation();
        $result->setStartLocation($startLocaltion);
        $result->setEndLocation($endLocation);

        return $result;
    }

    protected function readWrap($tokenStream, $wrapFn) {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $expression = $this->readExpressionHard($tokenStream, "missing expression");

        $endLocation = $tokenStream->current()->getEndLocation();
        
        $tuple = new Tuple([new Symbol($wrapFn), $expression]);
        $tuple->setStartLocation($startLocation);
        $tuple->setEndLocation($endLocation);

        return $tuple;
    }

    protected function readExpressionHard($tokenStream, $errorMessage) {
        $result = $this->readExpression($tokenStream);
        if (is_null($result)) {
            throw $this->buildReaderException($errorMessage);
        }

        return $result;
    }

    protected function readList(Generator $tokenStream, $term, $acc = [], $isUsingBrackets = false) {
        $startLocaltion = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        while ($tokenStream->valid()) {
            $token = $tokenStream->current();
            $this->readTokens[] = $token;

            if ($token instanceof WhitespaceToken) {
                $tokenStream->next();
                continue;
            }
            if ($token instanceof CommentToken) {
                $tokenStream->next();
                continue;
            }
            

            if ($token instanceof SyntaxToken && $token->getCode() === $term) {
                $endLocation = $token->getEndLocation();
                $tokenStream->next();
                $tuple = new Tuple($acc, $isUsingBrackets);
                $tuple->setStartLocation($startLocaltion);
                $tuple->setEndLocation($endLocation);

                return $tuple;
            } else {
                $acc[] = $this->readExpression($tokenStream);
            }
        }

        throw $this->buildReaderException('Unterminated list');
    }

    protected function parseAtom(AtomToken $token) {
        $word = $token->getCode();
        
        if ($word === 'true') {
            return true;
        } else if ($word === 'false') {
            return false;
        } else if ($word === 'nil') {
            return null;
        } else if ($word[0] === ':') {
            return new Keyword(substr($word, 1));
        } else if (preg_match("/([+-])?0[bB][01]+(_[01]+)*/", $word, $matches)) {
            // binary numbers
            $sign = (isset($matches[1]) && $matches[1] == '-') ? -1 : 1;
            return $sign * bindec(str_replace('_', '', $word));
        } else if (preg_match("/([+-])?0[xX][0-9a-fA-F]+(_[0-9a-fA-F]+)*/", $word, $matches)) {
            // hexdecimal numbers
            $sign = (isset($matches[1]) && $matches[1] == '-') ? -1 : 1;
            return $sign = hexdec(str_replace('_', '', $word));
        } else if (preg_match("/([+-])?0[0-7]+(_[0-7]+)*/", $word, $matches)) {
            // octal numbers
            $sign = (isset($matches[1]) && $matches[1] == '-') ? -1 : 1;
            return $sign * octdec(str_replace('_', '', $word));
        } else if (is_numeric($word)) {
            // normal numbers
            return $word + 0;
        } else {
            return new Symbol($word);
        }
    }

    protected function parseEscapedString($str) {
        $str = str_replace('\\"', '"', $str);

        return preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            function($matches) {
                $str = $matches[1];

                if (isset($this->stringReplacements[$str])) {
                    return $this->stringReplacements[$str];
                } elseif ('x' === $str[0] || 'X' === $str[0]) {
                    return chr(hexdec(substr($str, 1)));
                } elseif ('u' === $str[0]) {
                    return self::codePointToUtf8(hexdec($matches[2]));
                } else {
                    return chr(octdec($str));
                }
            },
            $str
        );
    }

    protected function codePointToUtf8(int $num) : string {
        if ($num <= 0x7F) {
            return chr($num);
        }
        if ($num <= 0x7FF) {
            return chr(($num>>6) + 0xC0) . chr(($num&0x3F) + 0x80);
        }
        if ($num <= 0xFFFF) {
            return chr(($num>>12) + 0xE0) . chr((($num>>6)&0x3F) + 0x80) . chr(($num&0x3F) + 0x80);
        }
        if ($num <= 0x1FFFFF) {
            return chr(($num>>18) + 0xF0) . chr((($num>>12)&0x3F) + 0x80)
                 . chr((($num>>6)&0x3F) + 0x80) . chr(($num&0x3F) + 0x80);
        }
        throw $this->buildReaderException('Invalid UTF-8 codepoint escape sequence: Codepoint too large');
    }

    private function getCodeSnippet($readTokens) {
        // TODO: Remove leading whitespace
        $code = $this->getCode($readTokens);

        return new CodeSnippet(
            $this->readTokens[0]->getStartLocation(),
            $this->readTokens[count($this->readTokens) - 1]->getEndLocation(),
            $code
        );
    }

    private function getCode($readTokens) {
        $code = '';
        foreach ($readTokens as $token) {
            return $code .= $token->getCode();
        }
        return $code;
    }

    private function buildReaderException($message) {
        $codeSnippet = $this->getCodeSnippet($this->readTokens);

        return new ReaderException(
            $message, 
            $codeSnippet->getStartLocation(),
            $codeSnippet->getEndLocation(),
            $codeSnippet
        );
    }
}