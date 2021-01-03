<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Parser\ExpressionParserFactoryInterface;
use Phel\Compiler\Parser\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Parser\Parser\ParserNode\CommentNode;
use Phel\Compiler\Parser\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Parser\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\Parser\ParserNode\StringNode;
use Phel\Compiler\Parser\Parser\ParserNode\WhitespaceNode;
use Phel\Exceptions\ParserException;
use Phel\Exceptions\StringParserException;

final class Parser implements ParserInterface
{
    private ExpressionParserFactoryInterface $parserFactory;

    public function __construct(ExpressionParserFactoryInterface $parserFactory)
    {
        $this->parserFactory = $parserFactory;
    }

    /**
     * Reads the next expression from the token stream.
     * If the token stream reaches the end, null is returned.
     *
     * @param TokenStream $tokenStream The token stream to read
     *
     * @throws ParserException
     */
    public function parseNext(TokenStream $tokenStream): ?NodeInterface
    {
        if (!$this->canParseToken($tokenStream)) {
            return null;
        }

        $tokenStream->clearReadTokens();

        return $this->readExpression($tokenStream);
    }

    private function canParseToken(TokenStream $tokenStream): bool
    {
        return $tokenStream->valid()
            && $tokenStream->current()->getType() !== Token::T_EOF;
    }

    /**
     * @throws ParserException
     */
    public function readExpression(TokenStream $tokenStream): NodeInterface
    {
        while ($tokenStream->valid()) {
            /** @var Token $token */
            $token = $tokenStream->current();

            switch ($token->getType()) {
                case Token::T_WHITESPACE:
                    $tokenStream->next();
                    return WhitespaceNode::createWithToken($token);

                case Token::T_NEWLINE:
                    $tokenStream->next();
                    return NewlineNode::createWithToken($token);

                case Token::T_COMMENT:
                    $tokenStream->next();
                    return CommentNode::createWithToken($token);

                case Token::T_ATOM:
                    $tokenStream->next();
                    return $this->parseAtomNode($token);

                case Token::T_STRING:
                    $tokenStream->next();
                    return $this->parseStringNode($token, $tokenStream);

                case Token::T_FN:
                case Token::T_OPEN_PARENTHESIS:
                    return $this->parseFnListNode($token, $tokenStream);

                case Token::T_ARRAY:
                case Token::T_OPEN_BRACKET:
                    return $this->parseArrayListNode($token, $tokenStream);

                case Token::T_TABLE:
                    return $this->parseTableListNode($token, $tokenStream);

                case Token::T_OPEN_BRACE:
                    throw $this->createParserException($tokenStream, 'Unexpected token: {');

                case Token::T_CLOSE_PARENTHESIS:
                case Token::T_CLOSE_BRACKET:
                case Token::T_CLOSE_BRACE:
                    throw $this->createParserException($tokenStream, 'Unterminated list');

                case Token::T_UNQUOTE_SPLICING:
                case Token::T_UNQUOTE:
                case Token::T_QUASIQUOTE:
                case Token::T_QUOTE:
                    return $this->parseQuoteNode($token, $tokenStream);

                case Token::T_CARET:
                    return $this->parseMetaNode($tokenStream);

                case Token::T_EOF:
                    throw $this->createParserException($tokenStream, 'Unterminated list');

                default:
                    throw $this->createParserException($tokenStream, 'Unhandled syntax token: ' . $token->getCode());
            }
        }

        throw $this->createParserException($tokenStream, 'Unterminated list');
    }

    private function parseAtomNode(Token $token): AbstractAtomNode
    {
        return $this->parserFactory
            ->createAtomParser()
            ->parse($token);
    }

    /**
     * @throws ParserException
     */
    private function parseStringNode(Token $token, TokenStream $tokenStream): StringNode
    {
        try {
            return $this->parserFactory
                ->createStringParser()
                ->parse($token);
        } catch (StringParserException $e) {
            throw $this->createParserException($tokenStream, $e->getMessage());
        }
    }

    private function createParserException(TokenStream $tokenStream, string $message): ParserException
    {
        return ParserException::forSnippet($tokenStream->getCodeSnippet(), $message);
    }

    /**
     * @throws ParserException
     */
    private function parseFnListNode(Token $token, TokenStream $tokenStream): ListNode
    {
        return $this->parserFactory
            ->createListParser($this)
            ->parse($tokenStream, Token::T_CLOSE_PARENTHESIS, $token->getType());
    }

    /**
     * @throws ParserException
     */
    private function parseArrayListNode(Token $token, TokenStream $tokenStream): ListNode
    {
        return $this->parserFactory
            ->createListParser($this)
            ->parse($tokenStream, Token::T_CLOSE_BRACKET, $token->getType());
    }

    /**
     * @throws ParserException
     */
    private function parseTableListNode(Token $token, TokenStream $tokenStream): ListNode
    {
        return $this->parserFactory
            ->createListParser($this)
            ->parse($tokenStream, Token::T_CLOSE_BRACE, $token->getType());
    }

    private function parseQuoteNode(Token $token, TokenStream $tokenStream): QuoteNode
    {
        return $this->parserFactory
            ->createQuoteParser($this)
            ->parse($tokenStream, $token->getType());
    }

    private function parseMetaNode(TokenStream $tokenStream): MetaNode
    {
        return $this->parserFactory
            ->createMetaParser($this)
            ->parse($tokenStream);
    }
}
