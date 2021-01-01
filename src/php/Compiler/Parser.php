<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Parser\ExpressionParser\StringParser;
use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Parser\ParserNode\CommentNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\StringNodeAbstract;
use Phel\Compiler\Parser\ParserNode\WhitespaceNode;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Exceptions\ParserException;
use Phel\Exceptions\StringParserException;

final class Parser implements ParserInterface
{

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
        if (!$tokenStream->valid()) {
            return null;
        }

        if ($tokenStream->current()->getType() === Token::T_EOF) {
            return null;
        }

        $tokenStream->clearReadTokens();

        return $this->readExpression($tokenStream);
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
                    return $this->createWhitespaceNode($token);

                case Token::T_NEWLINE:
                    $tokenStream->next();
                    return $this->createNewlineNode($token);

                case Token::T_COMMENT:
                    $tokenStream->next();
                    return $this->createCommentNode($token);

                case Token::T_ATOM:
                    $tokenStream->next();
                    return $this->createAtomNode($token);

                case Token::T_STRING:
                    $tokenStream->next();
                    return $this->createStringNode($token, $tokenStream);

                case Token::T_FN:
                case Token::T_OPEN_PARENTHESIS:
                    return $this->createFnListNode($tokenStream, $token);

                case Token::T_ARRAY:
                case Token::T_OPEN_BRACKET:
                    return $this->createArrayListNode($tokenStream, $token);

                case Token::T_TABLE:
                    return $this->createTableListNode($tokenStream, $token);

                case Token::T_OPEN_BRACE:
                    throw $this->buildParserException('Unexpected token: {', $tokenStream->getCodeSnippet());

                case Token::T_CLOSE_PARENTHESIS:
                case Token::T_CLOSE_BRACKET:
                case Token::T_CLOSE_BRACE:
                    throw $this->buildParserException('Unterminated list', $tokenStream->getCodeSnippet());

                case Token::T_UNQUOTE_SPLICING:
                case Token::T_UNQUOTE:
                case Token::T_QUASIQUOTE:
                case Token::T_QUOTE:
                    return $this->createQuoteNode($tokenStream, $token);

                case Token::T_CARET:
                    return $this->createMetaNode($tokenStream);

                case Token::T_EOF:
                    throw $this->buildParserException('Unterminated list', $tokenStream->getCodeSnippet());

                default:
                    throw $this->buildParserException('Unhandled syntax token: ' . $token->getCode(), $tokenStream->getCodeSnippet());
            }
        }

        throw $this->buildParserException('Unterminated list', $tokenStream->getCodeSnippet());
    }

    private function createWhitespaceNode(Token $token): WhitespaceNode
    {
        return new WhitespaceNode($token->getCode(), $token->getStartLocation(), $token->getEndLocation());
    }

    private function createNewlineNode(Token $token): NewlineNode
    {
        return new NewlineNode($token->getCode(), $token->getStartLocation(), $token->getEndLocation());
    }

    private function createCommentNode(Token $token): CommentNode
    {
        return new CommentNode($token->getCode(), $token->getStartLocation(), $token->getEndLocation());
    }

    private function createAtomNode(Token $token): AbstractAtomNode
    {
        return (new AtomParser())->parse($token);
    }

    private function createStringNode(Token $token, TokenStream $tokenStream): StringNodeAbstract
    {
        try {
            return (new StringParser($this))->parse($token);
        } catch (StringParserException $e) {
            throw $this->buildParserException($e->getMessage(), $tokenStream->getCodeSnippet());
        }
    }

    private function createFnListNode(TokenStream $tokenStream, Token $token): ListNode
    {
        return (new ListParser($this))->parse($tokenStream, Token::T_CLOSE_PARENTHESIS, $token->getType());
    }

    private function createArrayListNode(TokenStream $tokenStream, Token $token): ListNode
    {
        return (new ListParser($this))->parse($tokenStream, Token::T_CLOSE_BRACKET, $token->getType());
    }

    private function createTableListNode(TokenStream $tokenStream, Token $token): ListNode
    {
        return (new ListParser($this))->parse($tokenStream, Token::T_CLOSE_BRACE, $token->getType());
    }

    private function createQuoteNode(TokenStream $tokenStream, Token $token): QuoteNode
    {
        return (new QuoteParser($this))->parse($tokenStream, $token->getType());
    }

    private function createMetaNode(TokenStream $tokenStream): MetaNode
    {
        return (new MetaParser($this))->parse($tokenStream);
    }

    public function buildParserException(string $message, CodeSnippet $snippet): ParserException
    {
        return new ParserException($message, $snippet->getStartLocation(), $snippet->getEndLocation(), $snippet);
    }
}
