<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ExpressionParserFactoryInterface;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Domain\Parser\ParserNode\CommentNode;
use Phel\Compiler\Domain\Parser\ParserNode\FileNode;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\MetaNode;
use Phel\Compiler\Domain\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Domain\Parser\ParserNode\StringNode;
use Phel\Compiler\Domain\Parser\ParserNode\WhitespaceNode;

use function in_array;

final readonly class Parser implements ParserInterface
{
    private const TOKENS_THAT_SHOULD_STREAM_NEXT = [
        Token::T_WHITESPACE,
        Token::T_NEWLINE,
        Token::T_COMMENT,
        Token::T_ATOM,
        Token::T_STRING,
    ];

    public function __construct(
        private ExpressionParserFactoryInterface $parserFactory,
        private GlobalEnvironmentInterface $globalEnvironment,
    ) {
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
        while (($node = $this->parseNext($tokenStream)) instanceof NodeInterface) {
            $result[] = $node;
        }

        return FileNode::createFromChildren($result);
    }

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function readExpression(TokenStream $tokenStream): NodeInterface
    {
        if ($tokenStream->valid()) {
            $token = $tokenStream->current();

            $tokenType = $token->getType();

            if ($this->shouldTokenStreamGoNext($tokenType)) {
                $tokenStream->next();
            }

            return match ($tokenType) {
                Token::T_WHITESPACE => WhitespaceNode::createWithToken($token),
                Token::T_NEWLINE => NewlineNode::createWithToken($token),
                Token::T_COMMENT => CommentNode::createWithToken($token),
                Token::T_ATOM => $this->parseAtomNode($token, $tokenStream),
                Token::T_STRING => $this->parseStringNode($token, $tokenStream),
                Token::T_FN,
                Token::T_OPEN_PARENTHESIS => $this->parseFnListNode($token, $tokenStream),
                Token::T_OPEN_BRACKET => $this->parseArrayListNode($token, $tokenStream),
                Token::T_OPEN_BRACE => $this->parseMapListNode($token, $tokenStream),
                Token::T_CLOSE_PARENTHESIS,
                Token::T_CLOSE_BRACKET,
                Token::T_CLOSE_BRACE => throw $this->createUnexceptedParserException($tokenStream, $token, 'Unterminated list (BRACKETS)'),
                Token::T_UNQUOTE_SPLICING,
                Token::T_UNQUOTE,
                Token::T_QUASIQUOTE,
                Token::T_QUOTE => $this->parseQuoteNode($token, $tokenStream),
                Token::T_CARET => $this->parseMetaNode($tokenStream),
                Token::T_EOF => throw $this->createUnfinishedParserException($tokenStream, $token, 'Unterminated list (EOF)'),
                default => throw $this->createUnexceptedParserException($tokenStream, $token, 'Unhandled syntax token: ' . $token->getCode()),
            };
        }

        // Throw exception differently because we may have not $token
        $snippet = $tokenStream->getCodeSnippet();
        throw new UnfinishedParserException(
            'Unterminated list',
            $snippet,
            $snippet->getStartLocation(),
            $snippet->getEndLocation(),
        );
    }

    private function shouldTokenStreamGoNext(int $tokenType): bool
    {
        return in_array($tokenType, self::TOKENS_THAT_SHOULD_STREAM_NEXT, true);
    }

    private function canParseToken(TokenStream $tokenStream): bool
    {
        return $tokenStream->valid()
            && $tokenStream->current()->getType() !== Token::T_EOF;
    }

    private function parseAtomNode(Token $token, TokenStream $tokenStream): AbstractAtomNode
    {
        try {
            return $this->parserFactory
                ->createAtomParser($this->globalEnvironment)
                ->parse($token);
        } catch (KeywordParserException $keywordParserException) {
            throw $this->createUnexceptedParserException($tokenStream, $token, $keywordParserException->getMessage());
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
        } catch (StringParserException $stringParserException) {
            throw $this->createUnexceptedParserException($tokenStream, $token, $stringParserException->getMessage());
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
