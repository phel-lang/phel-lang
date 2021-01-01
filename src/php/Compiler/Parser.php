<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Generator;
use Phel\Compiler\ParserNode\AtomNode;
use Phel\Compiler\ParserNode\BooleanNode;
use Phel\Compiler\ParserNode\CommentNode;
use Phel\Compiler\ParserNode\KeywordNode;
use Phel\Compiler\ParserNode\ListNode;
use Phel\Compiler\ParserNode\MetaNode;
use Phel\Compiler\ParserNode\NewlineNode;
use Phel\Compiler\ParserNode\NilNode;
use Phel\Compiler\ParserNode\NodeInterface;
use Phel\Compiler\ParserNode\NumberNode;
use Phel\Compiler\ParserNode\QuoteNode;
use Phel\Compiler\ParserNode\StringNode;
use Phel\Compiler\ParserNode\SymbolNode;
use Phel\Compiler\ParserNode\TriviaNodeInterface;
use Phel\Compiler\ParserNode\WhitespaceNode;
use Phel\Compiler\ReadModel\CodeSnippet;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Exceptions\ParserException;

final class Parser implements ParserInterface
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

    /**
     * Reads the next expression from the token stream.
     *
     * If the token stream reaches the end, null is returned.
     *
     * @param Generator $tokenStream The token stream to read
     *
     * @throws ParserException
     */
    public function parseNext(Generator $tokenStream): ?NodeInterface
    {
        if (!$tokenStream->valid()) {
            return null;
        }

        if ($tokenStream->current()->getType() === Token::T_EOF) {
            return null;
        }

        $this->readTokens = [];
        return $this->readExpression($tokenStream);
    }

    /**
     * @throws ParserException
     *
     * @return NodeInterface
     */
    public function readExpression(Generator $tokenStream)
    {
        while ($tokenStream->valid()) {
            /** @var Token $token */
            $token = $tokenStream->current();
            $this->readTokens[] = $token;

            switch ($token->getType()) {

                case Token::T_WHITESPACE:
                    $tokenStream->next();
                    return new WhitespaceNode($token->getCode(), $token->getStartLocation(), $token->getEndLocation());

                case Token::T_NEWLINE:
                    $tokenStream->next();
                    return new NewlineNode($token->getCode(), $token->getStartLocation(), $token->getEndLocation());

                case Token::T_COMMENT:
                    $tokenStream->next();
                    return new CommentNode($token->getCode(), $token->getStartLocation(), $token->getEndLocation());

                case Token::T_ATOM:
                    $tokenStream->next();
                    return $this->parseAtom($token);

                case Token::T_STRING:
                    $tokenStream->next();
                    return $this->parseString($token);

                case Token::T_OPEN_PARENTHESIS:
                    return $this->readList($tokenStream, Token::T_CLOSE_PARENTHESIS, $token->getType());

                case Token::T_OPEN_BRACKET:
                    return $this->readList($tokenStream, Token::T_CLOSE_BRACKET, $token->getType());

                case Token::T_OPEN_BRACE:
                    throw $this->buildParserException('Unexpected token: {');

                case Token::T_CLOSE_PARENTHESIS:
                case Token::T_CLOSE_BRACKET:
                case Token::T_CLOSE_BRACE:
                    throw $this->buildParserException('Unterminated list');

                case Token::T_QUOTE:
                    return $this->readQuote($tokenStream, $token->getType());

                case Token::T_UNQUOTE:
                    return $this->readQuote($tokenStream, $token->getType());

                case Token::T_UNQUOTE_SPLICING:
                    return $this->readQuote($tokenStream, $token->getType());

                case Token::T_QUASIQUOTE:
                    return $this->readQuote($tokenStream, $token->getType());

                case Token::T_CARET:
                    return $this->readMeta($tokenStream);

                case Token::T_ARRAY:
                    return $this->readList($tokenStream, Token::T_CLOSE_BRACKET, $token->getType());

                case Token::T_TABLE:
                    return $this->readList($tokenStream, Token::T_CLOSE_BRACE, $token->getType());

                case Token::T_FN:
                    return $this->readList($tokenStream, Token::T_CLOSE_PARENTHESIS, $token->getType());

                case Token::T_EOF:
                    throw $this->buildParserException('Unterminated list');

                default:
                    throw $this->buildParserException('Unhandled syntax token: ' . $token->getCode());
            }
        }

        throw $this->buildParserException('Unterminated list');
    }

    private function readMeta(Generator $tokenStream): MetaNode
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $meta = $this->readExpression($tokenStream);
        $children = [];
        do {
            $object = $this->readExpression($tokenStream);
            $children[] = $object;
        } while ($object instanceof TriviaNodeInterface);

        $endLocation = $tokenStream->current()->getEndLocation();

        return new MetaNode($meta, $startLocation, $endLocation, $children);
    }

    private function readQuote(Generator $tokenStream, int $tokenType): QuoteNode
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();
        $expression = $this->readExpression($tokenStream);
        $endLocation = $tokenStream->current()->getEndLocation();

        return new QuoteNode($tokenType, $startLocation, $endLocation, $expression);
    }

    private function readList(Generator $tokenStream, int $endTokenType, int $tokenType): ListNode
    {
        $acc = [];
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        while ($tokenStream->valid()) {
            $token = $tokenStream->current();

            if ($token->getType() === $endTokenType) {
                $this->readTokens[] = $token;
                $endLocation = $token->getEndLocation();
                $tokenStream->next();

                return new ListNode($tokenType, $startLocation, $endLocation, $acc);
            } else {
                $acc[] = $this->readExpression($tokenStream);
            }
        }

        throw $this->buildParserException('Unterminated list');
    }

    private function parseAtom(Token $token): AtomNode
    {
        $word = $token->getCode();

        if ($word === 'true') {
            return new BooleanNode($word, $token->getStartLocation(), $token->getEndLocation(), true);
            return true;
        }

        if ($word === 'false') {
            return new BooleanNode($word, $token->getStartLocation(), $token->getEndLocation(), false);
        }

        if ($word === 'nil') {
            return new NilNode($word, $token->getStartLocation(), $token->getEndLocation(), null);
            return null;
        }

        if (strpos($word, ':') === 0) {
            return new KeywordNode($word, $token->getStartLocation(), $token->getEndLocation(), new Keyword(substr($word, 1)));
        }

        if (preg_match('/^([+-])?0[bB][01]+(_[01]+)*$/', $word, $matches)) {
            // binary numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $sign * bindec(str_replace('_', '', $word)));
        }

        if (preg_match('/^([+-])?0[xX][0-9a-fA-F]+(_[0-9a-fA-F]+)*$/', $word, $matches)) {
            // hexdecimal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $sign * hexdec(str_replace('_', '', $word)));
        }

        if (preg_match('/^([+-])?0[0-7]+(_[0-7]+)*$/', $word, $matches)) {
            // octal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $sign * octdec(str_replace('_', '', $word)));
        }

        if (is_numeric($word)) {
            return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $word + 0);
        }

        // Symbol
        return new SymbolNode($word, $token->getStartLocation(), $token->getEndLocation(), Symbol::create($word));
    }

    private function parseString(Token $token): StringNode
    {
        return new StringNode($token->getCode(), $token->getStartLocation(), $token->getEndLocation(), $this->parseEscapedString(substr($token->getCode(), 1, -1)));
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
        throw $this->buildParserException('Invalid UTF-8 codepoint escape sequence: Codepoint too large');
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

    private function buildParserException(string $message): ParserException
    {
        $codeSnippet = $this->getCodeSnippet($this->readTokens);

        return new ParserException(
            $message,
            $codeSnippet->getStartLocation(),
            $codeSnippet->getEndLocation(),
            $codeSnippet
        );
    }
}
