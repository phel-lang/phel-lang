<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Parser;
use Phel\Compiler\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Parser\ExpressionParser\StringParser;
use Phel\Compiler\Parser\ParserNode\AtomNode;
use Phel\Compiler\Parser\ParserNode\CommentNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\StringNode;
use Phel\Compiler\Parser\ParserNode\WhitespaceNode;
use Phel\Compiler\Token;
use Phel\Compiler\TokenStream;
use Phel\Exceptions\ParserException;
use Phel\Exceptions\StringParserException;

final class ParserFactory
{
    public function createWhitespaceNode(Token $token): WhitespaceNode
    {
        return WhitespaceNode::createWithToken($token);
    }

    public function createNewlineNode(Token $token): NewlineNode
    {
        return NewlineNode::createWithToken($token);
    }

    public function createCommentNode(Token $token): CommentNode
    {
        return CommentNode::createWithToken($token);
    }

    public function createAtomNode(Token $token): AtomNode
    {
        return (new AtomParser())
            ->parse($token);
    }

    public function createStringNode(Parser $parser, Token $token, TokenStream $tokenStream): StringNode
    {
        try {
            return (new StringParser($parser))
                ->parse($token);
        } catch (StringParserException $e) {
            throw $this->createParserException($tokenStream, $e->getMessage());
        }
    }

    private function createParserException(TokenStream $tokenStream, string $message): ParserException
    {
        return ParserException::forSnippet($tokenStream->getCodeSnippet(), $message);
    }

    public function createFnListNode(Parser $parser, Token $token, TokenStream $tokenStream): ListNode
    {
        return (new ListParser($parser))
            ->parse($tokenStream, Token::T_CLOSE_PARENTHESIS, $token->getType());
    }

    public function createArrayListNode(Parser $parser, Token $token, TokenStream $tokenStream): ListNode
    {
        return (new ListParser($parser))
            ->parse($tokenStream, Token::T_CLOSE_BRACKET, $token->getType());
    }

    public function createTableListNode(Parser $parser, Token $token, TokenStream $tokenStream): ListNode
    {
        return (new ListParser($parser))
            ->parse($tokenStream, Token::T_CLOSE_BRACE, $token->getType());
    }

    public function createQuoteNode(Parser $parser, Token $token, TokenStream $tokenStream): QuoteNode
    {
        return (new QuoteParser($parser))
            ->parse($tokenStream, $token->getType());
    }

    public function createMetaNode(Parser $parser, TokenStream $tokenStream): MetaNode
    {
        return (new MetaParser($parser))
            ->parse($tokenStream);
    }
}
