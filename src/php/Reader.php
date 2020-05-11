<?php

namespace Phel;

use Generator;
use Phel\Exceptions\ReaderException;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\Stream\CodeSnippet;

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

        if ($tokenStream->current()->getType() == Token::T_EOF) {
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

            switch ($token->getType()) {
                case Token::T_WHITESPACE:
                case Token::T_COMMENT:
                    $tokenStream->next();
                    break;

                case Token::T_ATOM:
                    $tokenStream->next();
                    $result = $this->parseAtom($token);

                    if ($result instanceof Phel) {
                        $result->setStartLocation($token->getStartLocation());
                        $result->setEndLocation($token->getEndLocation());
                    }
                    return $result;

                case Token::T_STRING:
                    $tokenStream->next();
                    return $this->parseEscapedString(substr($token->getCode(), 1, -1));

                case Token::T_OPEN_PARENTHESIS:
                    return $this->readList($tokenStream, Token::T_CLOSE_PARENTHESIS);

                case Token::T_OPEN_BRACKET:
                    return $this->readList($tokenStream, Token::T_CLOSE_BRACKET, [], true);

                case Token::T_OPEN_BRACE:
                    throw $this->buildReaderException('Expected token: {');

                case Token::T_CLOSE_PARENTHESIS:
                case Token::T_CLOSE_BRACKET:
                case Token::T_CLOSE_BRACE:
                    throw $this->buildReaderException('Unterminated list');

                case Token::T_QUOTE:
                    return $this->readWrap($tokenStream, "quote");

                case Token::T_UNQUOTE:
                    return $this->readWrap($tokenStream, "unquote");

                case Token::T_UNQUOTE_SPLICING:
                    return $this->readWrap($tokenStream, "unquote-splicing");

                case Token::T_QUASIQUOTE:
                    return $this->readQuasiquote($tokenStream);

                case Token::T_ARRAY:
                    return $this->readList($tokenStream, Token::T_CLOSE_BRACKET, [new Symbol('array')]);

                case Token::T_TABLE:
                    $tuple = $this->readList($tokenStream, Token::T_CLOSE_BRACE, [new Symbol('table')]);
                    if (count($tuple) % 2 == 0) {
                        throw $this->buildReaderException("Tables must have an even number of parameters");
                    }
                    return $tuple;

                case Token::T_EOF:
                    throw $this->buildReaderException("Unterminatend list");

                default:
                    throw $this->buildReaderException("Unhandled syntax token: " . $token->getCode());
            }
        }

        throw $this->buildReaderException("Unterminatend list");
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
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        while ($tokenStream->valid()) {
            $token = $tokenStream->current();

            switch ($token->getType()) {
                case Token::T_WHITESPACE:
                case Token::T_COMMENT:
                    $this->readTokens[] = $token;
                    $tokenStream->next();
                    break;

                case $term:
                    $this->readTokens[] = $token;
                    $endLocation = $token->getEndLocation();
                    $tokenStream->next();
                    $tuple = new Tuple($acc, $isUsingBrackets);
                    $tuple->setStartLocation($startLocation);
                    $tuple->setEndLocation($endLocation);

                    return $tuple;

                default:
                    $acc[] = $this->readExpression($tokenStream);
            }
        }

        throw $this->buildReaderException('Unterminated list');
    }

    protected function parseAtom(Token $token) {
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

    protected function parseEscapedString(string $str) {
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
        $tokens = $this->removeLeadingWhitespace($readTokens);
        $code = $this->getCode($tokens);

        return new CodeSnippet(
            $tokens[0]->getStartLocation(),
            $tokens[count($tokens) - 1]->getEndLocation(),
            $code
        );
    }

    private function getCode($readTokens) {
        $code = '';
        foreach ($readTokens as $token) {
            $code .= $token->getCode();
        }
        return $code;
    }

    private function removeLeadingWhitespace($readTokens) {
        $result = [];
        $leadingWhitespace = true;
        foreach ($readTokens as $token) {
            if (!($leadingWhitespace && ($token->getType() == Token::T_WHITESPACE || $token->getType() == Token::T_COMMENT))) {
                $leadingWhitespace = false;
                $result[] = $token;
            }
        }

        return $result;
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