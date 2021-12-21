<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Parser\Exceptions\StringParserException;
use Phel\Compiler\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Parser\ParserNode\CommentNode;
use Phel\Compiler\Parser\ParserNode\FileNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\StringNode;
use Phel\Compiler\Parser\ParserNode\WhitespaceNode;

final class Parser implements ParserInterface
{
    private ExpressionParserFactoryInterface $parserFactory;
    private GlobalEnvironmentInterface $globalEnvironment;

    public function __construct(
        ExpressionParserFactoryInterface $parserFactory,
        GlobalEnvironmentInterface $globalEnvironment
    ) {
        $this->parserFactory = $parserFactory;
        $this->globalEnvironment = $globalEnvironment;
    }

    /**
     * Reads the next expression from the token stream.
     * If the token stream reaches the end, null is returned.
     *
     * @param TokenStream $tokenStream The token stream to read
     *
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseNext(TokenStream $tokenStream): ?NodeInterface
    {
        if (!$this->canParseToken($tokenStream)) {
            return null;
        }

        $tokenStream->clearReadTokens();

        return $this->readExpression($tokenStream);
    }

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseAll(TokenStream $tokenStream): FileNode
    {
        $result = [];
        while ($node = $this->parseNext($tokenStream)) {
            $result[] = $node;
        }

        return FileNode::createFromChildren($result);
    }

    private function canParseToken(TokenStream $tokenStream): bool
    {
        return $tokenStream->valid()
            && $tokenStream->current()->getType() !== Token::T_EOF;
    }

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
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
                    return $this->parseAtomNode($token, $tokenStream);

                case Token::T_STRING:
                    $tokenStream->next();
                    return $this->parseStringNode($token, $tokenStream);

                case Token::T_FN:
                case Token::T_OPEN_PARENTHESIS:
                    return $this->parseFnListNode($token, $tokenStream);

                case Token::T_OPEN_BRACKET:
                    return $this->parseArrayListNode($token, $tokenStream);

                case Token::T_OPEN_BRACE:
                    return $this->parseMapListNode($token, $tokenStream);

                case Token::T_CLOSE_PARENTHESIS:
                case Token::T_CLOSE_BRACKET:
                case Token::T_CLOSE_BRACE:
                    throw $this->createUnexceptedParserException($tokenStream, $token, 'Unterminated list (BRACKETS)');

                case Token::T_UNQUOTE_SPLICING:
                case Token::T_UNQUOTE:
                case Token::T_QUASIQUOTE:
                case Token::T_QUOTE:
                    return $this->parseQuoteNode($token, $tokenStream);

                case Token::T_CARET:
                    return $this->parseMetaNode($tokenStream);

                case Token::T_EOF:
                    throw $this->createUnfinishedParserException($tokenStream, $token, 'Unterminated list (EOF)');

                default:
                    throw $this->createUnexceptedParserException($tokenStream, $token, 'Unhandled syntax token: ' . $token->getCode());
            }
        }

        // Throw exception differently because we may have not $token
        $snippet = $tokenStream->getCodeSnippet();
        throw new UnfinishedParserException(
            'Unterminated list',
            $snippet,
            $snippet->getStartLocation(),
            $snippet->getEndLocation()
        );
    }

    private function parseAtomNode(Token $token, TokenStream $tokenStream): AbstractAtomNode
    {
        try {
            return $this->parserFactory
                ->createAtomParser($this->globalEnvironment)
                ->parse($token);
        } catch (KeywordParserException $e) {
            throw $this->createUnexceptedParserException($tokenStream, $token, $e->getMessage());
        }
    }

    /**
     * @throws UnexpectedParserException
     */
    private function parseStringNode(Token $token, TokenStream $tokenStream): StringNode
    {
        try {
            return $this->parserFactory
                ->createStringParser()
                ->parse($token);
        } catch (StringParserException $e) {
            throw $this->createUnexceptedParserException($tokenStream, $token, $e->getMessage());
        }
    }

    private function createUnexceptedParserException(TokenStream $tokenStream, Token $currentToken, string $message): UnexpectedParserException
    {
        return UnexpectedParserException::forSnippet($tokenStream->getCodeSnippet(), $currentToken, $message);
    }

    private function createUnfinishedParserException(TokenStream $tokenStream, Token $currentToken, string $message): UnfinishedParserException
    {
        return UnfinishedParserException::forSnippet($tokenStream->getCodeSnippet(), $currentToken, $message);
    }

    /**
     * @throws UnfinishedParserException
     */
    private function parseFnListNode(Token $token, TokenStream $tokenStream): ListNode
    {
        return $this->parserFactory
            ->createListParser($this)
            ->parse($tokenStream, Token::T_CLOSE_PARENTHESIS, $token->getType());
    }

    /**
     * @throws UnfinishedParserException
     */
    private function parseArrayListNode(Token $token, TokenStream $tokenStream): ListNode
    {
        return $this->parserFactory
            ->createListParser($this)
            ->parse($tokenStream, Token::T_CLOSE_BRACKET, $token->getType());
    }

    private function parseMapListNode(Token $token, TokenStream $tokenStream): ListNode
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
