<?php

namespace Phel;

use Generator;
use Phel\Exceptions\ReaderException;
use Phel\Lang\IMeta;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Stream\CodeSnippet;

class Reader {

    /**
     * @var array
     */
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

    /**
     * @var Token[]
     */
    private $readTokens = [];

    /**
     * Reads the next expression from the token stream.
     * 
     * If the token stream reaches the end, false is returned.
     * 
     * @param Generator $tokenStream The token stream to read.
     * 
     * @return ReaderResult|false
     */
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

    /**
     * @return Phel|null|scalar
     */
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
                    return $this->readList($tokenStream, Token::T_CLOSE_BRACKET, true);

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

                case Token::T_CARET:
                    return $this->readMeta($tokenStream);

                case Token::T_ARRAY:
                    $tuple = $this->readList($tokenStream, Token::T_CLOSE_BRACKET);
                    $arr = new PhelArray($tuple->toArray());
                    $arr->setStartLocation($tuple->getStartLocation());
                    $arr->setEndLocation($tuple->getEndLocation());
                    return $arr;

                case Token::T_TABLE:
                    $tuple = $this->readList($tokenStream, Token::T_CLOSE_BRACE);
                    if (count($tuple) % 2 == 1) {
                        throw $this->buildReaderException("Tables must have an even number of parameters");
                    }
                    $table = Table::fromKVArray($tuple->toArray());
                    $table->setStartLocation($tuple->getStartLocation());
                    $table->setEndLocation($tuple->getEndLocation());
                    return $table;

                case Token::T_EOF:
                    throw $this->buildReaderException("Unterminatend list");

                default:
                    throw $this->buildReaderException("Unhandled syntax token: " . $token->getCode());
            }
        }

        throw $this->buildReaderException("Unterminatend list");
    }

    /**
     * @return Phel|scalar|null
     */
    protected function readQuasiquote(Generator $tokenStream) {
        $startLocaltion = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $expression = $this->readExpressionHard($tokenStream, "missing expression");
        $q = new Quasiquote();
        $result = $q->quasiquote($expression);

        if ($result instanceof Phel) {
            $endLocation = $tokenStream->current()->getEndLocation();
            $result->setStartLocation($startLocaltion);
            $result->setEndLocation($endLocation);
        }

        return $result;
    }

    protected function readMeta(Generator $tokenStream) {
        $tokenStream->next();

        $meta = $this->readExpressionHard($tokenStream, "missing meta expression");
        if (is_string($meta) || $meta instanceof Symbol) {
            $meta = Table::fromKVs(new Keyword('tag'), $meta);
        } else if ($meta instanceof Keyword) {
            $meta = Table::fromKVs($meta, true);
        } else if (!$meta instanceof Table) {
            throw $this->buildReaderException('Metadata must be a Symbol, String, Keyword or Table');
        }
        $object = $this->readExpressionHard($tokenStream, "missing object expression for meta data");

        if ($object instanceof IMeta) {
            $objMeta = $object->getMeta();
            foreach ($meta as $k => $v) {
                $objMeta[$k] = $v;
            }
            $object->setMeta($objMeta);
        } else {
            throw $this->buildReaderException('Metadata can only applied to classes that implement IMeta');
        }

        return $object;
    }

    protected function readWrap(Generator $tokenStream, string $wrapFn): Tuple {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $expression = $this->readExpressionHard($tokenStream, "missing expression");

        $endLocation = $tokenStream->current()->getEndLocation();
        
        $tuple = new Tuple([new Symbol($wrapFn), $expression]);
        $tuple->setStartLocation($startLocation);
        $tuple->setEndLocation($endLocation);

        return $tuple;
    }

    /**
     * @return string|null|boolean|float|int|Phel
     */
    protected function readExpressionHard(Generator $tokenStream, string $errorMessage) {
        $result = $this->readExpression($tokenStream);
        if (is_null($result)) {
            throw $this->buildReaderException($errorMessage);
        }

        return $result;
    }

    protected function readList(Generator $tokenStream, int $endTokenType, bool $isUsingBrackets = false): Tuple {
        $acc = [];
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

                case $endTokenType:
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

    /**
     * Parse a Atom.
     * 
     * @param Token $token The token that was identified as atom.
     * 
     * @return boolean|null|Keyword|Symbol|string|int|float
     */
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

    protected function parseEscapedString(string $str): string {
        $str = str_replace('\\"', '"', $str);

        return preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            function(array $matches): string {
                $str = $matches[1];

                if (isset($this->stringReplacements[$str])) {
                    return $this->stringReplacements[$str];
                } elseif ('x' === $str[0] || 'X' === $str[0]) {
                    return chr(hexdec(substr($str, 1)));
                } elseif ('u' === $str[0]) {
                    return self::codePointToUtf8(hexdec($matches[2]));
                } else {
                    /** @var int $n */
                    $n = octdec($str);
                    return chr($n);
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

    /**
     * Create a CodeSnippet from a list of Tokens.
     * 
     * @param Token[] $readTokens The tokens read so far.
     * 
     * @return CodeSnippet
     */
    private function getCodeSnippet($readTokens): CodeSnippet {
        $tokens = $this->removeLeadingWhitespace($readTokens);
        $code = $this->getCode($tokens);

        return new CodeSnippet(
            $tokens[0]->getStartLocation(),
            $tokens[count($tokens) - 1]->getEndLocation(),
            $code
        );
    }

    /**
     * Concatinates all Token to a string.
     * 
     * @param Token[] $readTokens The tokens read so far.
     * 
     * @return string
     */
    private function getCode($readTokens): string {
        $code = '';
        foreach ($readTokens as $token) {
            $code .= $token->getCode();
        }
        return $code;
    }

    /**
     * Removes all leading whitespace and comment tokens
     * 
     * @param Token[] $readTokens The tokens read so far
     * 
     * @return Token[]
     */
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

    private function buildReaderException(string $message): ReaderException {
        $codeSnippet = $this->getCodeSnippet($this->readTokens);

        return new ReaderException(
            $message,
            $codeSnippet->getStartLocation(),
            $codeSnippet->getEndLocation(),
            $codeSnippet
        );
    }
}