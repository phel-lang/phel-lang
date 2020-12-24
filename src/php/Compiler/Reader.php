<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Generator;
use Phel\Compiler\ReadModel\CodeSnippet;
use Phel\Compiler\ReadModel\ReaderResult;
use Phel\Exceptions\ReaderException;
use Phel\Lang\AbstractType;
use Phel\Lang\IMeta;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

final class Reader implements ReaderInterface
{
    private const STRING_REPLACEMENTS = [
        '\\' => '\\',
        '$' => '$',
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'f' => "\f",
        'v' => "\v",
        'e' => "\x1B",
    ];

    /** @var Token[] */
    private array $readTokens = [];

    /** @var Symbol[]|null */
    private ?array $fnArgs = null;

    private QuasiquoteTransformerInterface $quasiquoteTransformer;

    public function __construct(QuasiquoteTransformerInterface $quasiquoteTransformer)
    {
        $this->quasiquoteTransformer = $quasiquoteTransformer;
    }

    /**
     * Reads the next expression from the token stream.
     *
     * If the token stream reaches the end, null is returned.
     *
     * @param Generator $tokenStream The token stream to read
     *
     * @throws ReaderException
     */
    public function readNext(Generator $tokenStream): ?ReaderResult
    {
        $this->readWhitespace($tokenStream);

        if (!$tokenStream->valid()) {
            return null;
        }

        if ($tokenStream->current()->getType() === Token::T_EOF) {
            return null;
        }

        $this->readTokens = [];
        $ast = $this->readExpression($tokenStream);

        return new ReaderResult(
            $ast,
            $this->getCodeSnippet($this->readTokens)
        );
    }

    private function readWhitespace(Generator $tokenStream): void
    {
        while ($tokenStream->valid()) {
            $token = $tokenStream->current();
            $this->readTokens[] = $token;

            switch ($token->getType()) {
                case Token::T_WHITESPACE:
                case Token::T_COMMENT:
                    $tokenStream->next();
                    break;
                default:
                    return;
            }
        }
    }

    /**
     * @throws ReaderException
     *
     * @return AbstractType|string|float|int|bool|null
     */
    public function readExpression(Generator $tokenStream)
    {
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

                    if ($result instanceof AbstractType) {
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
                    return $this->readList($tokenStream, Token::T_CLOSE_BRACKET, $isUsingBrackets = true);

                case Token::T_OPEN_BRACE:
                    throw $this->buildReaderException('Unexpected token: {');

                case Token::T_CLOSE_PARENTHESIS:
                case Token::T_CLOSE_BRACKET:
                case Token::T_CLOSE_BRACE:
                    throw $this->buildReaderException('Unterminated list');

                case Token::T_QUOTE:
                    return $this->readWrap($tokenStream, Symbol::NAME_QUOTE);

                case Token::T_UNQUOTE:
                    return $this->readWrap($tokenStream, Symbol::NAME_UNQUOTE);

                case Token::T_UNQUOTE_SPLICING:
                    return $this->readWrap($tokenStream, Symbol::NAME_UNQUOTE_SPLICING);

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
                    if (count($tuple) % 2 === 1) {
                        throw $this->buildReaderException('Tables must have an even number of parameters');
                    }
                    $table = Table::fromKVArray($tuple->toArray());
                    $table->setStartLocation($tuple->getStartLocation());
                    $table->setEndLocation($tuple->getEndLocation());
                    return $table;

                case Token::T_FN:
                    $this->fnArgs = [];
                    $body = $this->readList($tokenStream, Token::T_CLOSE_PARENTHESIS);

                    if (!empty($this->fnArgs)) {
                        $maxParams = max(array_keys($this->fnArgs));
                        $params = [];
                        for ($i = 1; $i <= $maxParams; $i++) {
                            if (isset($this->fnArgs[$i])) {
                                $params[] = Symbol::create($this->fnArgs[$i]->getName());
                            } else {
                                $params[] = Symbol::gen('__short_fn_undefined_');
                            }
                        }

                        if (isset($this->fnArgs[0])) {
                            $params[] = Symbol::create('&');
                            $params[] = Symbol::create($this->fnArgs[0]->getName());
                        }
                    } else {
                        $params = [];
                    }

                    $this->fnArgs = null;
                    return Tuple::create(Symbol::create(Symbol::NAME_FN), new Tuple($params, true), $body);

                case Token::T_EOF:
                    throw $this->buildReaderException('Unterminated list');

                default:
                    throw $this->buildReaderException('Unhandled syntax token: ' . $token->getCode());
            }
        }

        throw $this->buildReaderException('Unterminated list');
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readQuasiquote(Generator $tokenStream)
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $expression = $this->readExpressionHard($tokenStream, 'missing expression');
        $result = $this->quasiquoteTransformer->transform($expression);

        if ($result instanceof AbstractType) {
            $endLocation = $tokenStream->current()->getEndLocation();
            $result->setStartLocation($startLocation);
            $result->setEndLocation($endLocation);
        }

        return $result;
    }

    /**
     * @return AbstractType|string|float|int|bool
     */
    private function readMeta(Generator $tokenStream)
    {
        $tokenStream->next();

        $meta = $this->readExpressionHard($tokenStream, 'missing meta expression');
        if (is_string($meta) || $meta instanceof Symbol) {
            $meta = Table::fromKVs(new Keyword('tag'), $meta);
        } elseif ($meta instanceof Keyword) {
            $meta = Table::fromKVs($meta, true);
        } elseif (!$meta instanceof Table) {
            throw $this->buildReaderException('Metadata must be a Symbol, String, Keyword or Table');
        }
        $object = $this->readExpressionHard($tokenStream, 'missing object expression for meta data');

        if (!$object instanceof IMeta) {
            throw $this->buildReaderException('Metadata can only applied to classes that implement IMeta');
        }

        $objMeta = $object->getMeta();
        foreach ($meta as $k => $v) {
            if ($k) {
                $objMeta[$k] = $v;
            }
        }
        $object->setMeta($objMeta);

        return $object;
    }

    private function readWrap(Generator $tokenStream, string $wrapFn): Tuple
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $expression = $this->readExpressionHard($tokenStream, 'missing expression');

        $endLocation = $tokenStream->current()->getEndLocation();

        $tuple = new Tuple([Symbol::create($wrapFn), $expression]);
        $tuple->setStartLocation($startLocation);
        $tuple->setEndLocation($endLocation);

        return $tuple;
    }

    /**
     * @return AbstractType|string|float|int|bool
     */
    private function readExpressionHard(Generator $tokenStream, string $errorMessage)
    {
        $result = $this->readExpression($tokenStream);
        if (is_null($result)) {
            throw $this->buildReaderException($errorMessage);
        }

        return $result;
    }

    private function readList(Generator $tokenStream, int $endTokenType, bool $isUsingBrackets = false): Tuple
    {
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
     * @param Token $token The token that was identified as atom
     *
     * @return bool|null|Keyword|Symbol|string|int|float
     */
    private function parseAtom(Token $token)
    {
        $word = $token->getCode();

        if ($word === 'true') {
            return true;
        }

        if ($word === 'false') {
            return false;
        }

        if ($word === 'nil') {
            return null;
        }

        if (strpos($word, ':') === 0) {
            return new Keyword(substr($word, 1));
        }

        if (preg_match('/^([+-])?0[bB][01]+(_[01]+)*$/', $word, $matches)) {
            // binary numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return $sign * bindec(str_replace('_', '', $word));
        }

        if (preg_match('/^([+-])?0[xX][0-9a-fA-F]+(_[0-9a-fA-F]+)*$/', $word, $matches)) {
            // hexdecimal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return $sign * hexdec(str_replace('_', '', $word));
        }

        if (preg_match('/^([+-])?0[0-7]+(_[0-7]+)*$/', $word, $matches)) {
            // octal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return $sign * octdec(str_replace('_', '', $word));
        }

        if (is_numeric($word)) {
            // normal numbers
            return $word + 0;
        }

        // Symbol
        return $this->readSymbol($word);
    }

    private function readSymbol(string $word): Symbol
    {
        if (!is_array($this->fnArgs)) {
            return Symbol::create($word);
        }

        // Special case: We read an anonymous function
        if ($word === '$') {
            if (isset($this->fnArgs[1])) {
                return Symbol::create($this->fnArgs[1]->getName());
            }
            $sym = Symbol::gen('__short_fn_1_');
            $this->fnArgs[1] = $sym;
            return $sym;
        }

        if ($word === '$&') {
            if (isset($this->fnArgs[0])) {
                return Symbol::create($this->fnArgs[0]->getName());
            }
            $sym = Symbol::gen('__short_fn_rest_');
            $this->fnArgs[0] = $sym;
            return $sym;
        }

        if (preg_match('/\$([1-9][0-9]*)/', $word, $matches)) {
            $number = (int)$matches[1];
            if (isset($this->fnArgs[$number])) {
                return Symbol::create($this->fnArgs[$number]->getName());
            }
            $sym = Symbol::gen('__short_fn_' . $number . '_');
            $this->fnArgs[$number] = $sym;
            return $sym;
        }

        return Symbol::create($word);
    }

    private function parseEscapedString(string $str): string
    {
        $str = str_replace('\\"', '"', $str);

        return preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            function (array $matches): string {
                $str = $matches[1];

                if (isset(self::STRING_REPLACEMENTS[$str])) {
                    return self::STRING_REPLACEMENTS[$str];
                }

                if ('x' === $str[0] || 'X' === $str[0]) {
                    return chr(hexdec(substr($str, 1)));
                }

                if ('u' === $str[0]) {
                    return self::codePointToUtf8(hexdec($matches[2]));
                }

                /** @var int $n */
                $n = octdec($str);
                return chr($n);
            },
            $str
        );
    }

    private function codePointToUtf8(int $num): string
    {
        if ($num <= 0x7F) {
            return chr($num);
        }
        if ($num <= 0x7FF) {
            return chr(($num >> 6) + 0xC0) . chr(($num & 0x3F) + 0x80);
        }
        if ($num <= 0xFFFF) {
            return chr(($num >> 12) + 0xE0) . chr((($num >> 6) & 0x3F) + 0x80) . chr(($num & 0x3F) + 0x80);
        }
        if ($num <= 0x1FFFFF) {
            return chr(($num >> 18) + 0xF0) . chr((($num >> 12) & 0x3F) + 0x80)
                . chr((($num >> 6) & 0x3F) + 0x80) . chr(($num & 0x3F) + 0x80);
        }
        throw $this->buildReaderException('Invalid UTF-8 codepoint escape sequence: Codepoint too large');
    }

    /**
     * Create a CodeSnippet from a list of Tokens.
     *
     * @param Token[] $readTokens The tokens read so far
     */
    private function getCodeSnippet($readTokens): CodeSnippet
    {
        $tokens = $this->removeLeadingWhitespace($readTokens);
        $code = $this->getCode($tokens);

        return new CodeSnippet(
            $tokens[0]->getStartLocation(),
            $tokens[count($tokens) - 1]->getEndLocation(),
            $code
        );
    }

    /**
     * Concatenates all Token to a string.
     *
     * @param Token[] $readTokens The tokens read so far
     */
    private function getCode($readTokens): string
    {
        $code = '';
        foreach ($readTokens as $token) {
            $code .= $token->getCode();
        }
        return $code;
    }

    /**
     * Removes all leading whitespace and comment tokens.
     *
     * @param Token[] $readTokens The tokens read so far
     *
     * @return Token[]
     */
    private function removeLeadingWhitespace($readTokens): array
    {
        $result = [];
        $leadingWhitespace = true;
        foreach ($readTokens as $token) {
            if (!($leadingWhitespace
                && in_array($token->getType(), [Token::T_WHITESPACE, Token::T_COMMENT], true))
            ) {
                $leadingWhitespace = false;
                $result[] = $token;
            }
        }

        return $result;
    }

    private function buildReaderException(string $message): ReaderException
    {
        $codeSnippet = $this->getCodeSnippet($this->readTokens);

        return new ReaderException(
            $message,
            $codeSnippet->getStartLocation(),
            $codeSnippet->getEndLocation(),
            $codeSnippet
        );
    }
}
