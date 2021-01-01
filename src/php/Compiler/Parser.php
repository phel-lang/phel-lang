<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Parser\ParserFactory;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Exceptions\ParserException;

final class Parser implements ParserInterface
{
    private ParserFactory $parserFactory;

    public function __construct(ParserFactory $parserFactory)
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
                    return $this->parserFactory->createWhitespaceNode($token);

                case Token::T_NEWLINE:
                    $tokenStream->next();
                    return $this->parserFactory->createNewlineNode($token);

                case Token::T_COMMENT:
                    $tokenStream->next();
                    return $this->parserFactory->createCommentNode($token);

                case Token::T_ATOM:
                    $tokenStream->next();
                    return $this->parserFactory->createAtomNode($token);

                case Token::T_STRING:
                    $tokenStream->next();
                    return $this->parserFactory->createStringNode($this, $token, $tokenStream);

                case Token::T_FN:
                case Token::T_OPEN_PARENTHESIS:
                    return $this->parserFactory->createFnListNode($this, $token, $tokenStream);

                case Token::T_ARRAY:
                case Token::T_OPEN_BRACKET:
                    return $this->parserFactory->createArrayListNode($this, $token, $tokenStream);

                case Token::T_TABLE:
                    return $this->parserFactory->createTableListNode($this, $token, $tokenStream);

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
                    return $this->parserFactory->createQuoteNode($this, $token, $tokenStream);

                case Token::T_CARET:
                    return $this->parserFactory->createMetaNode($this, $tokenStream);

                case Token::T_EOF:
                    throw $this->createParserException($tokenStream, 'Unterminated list');

                default:
                    throw $this->createParserException($tokenStream, 'Unhandled syntax token: ' . $token->getCode());
            }
        }

        throw $this->createParserException($tokenStream, 'Unterminated list');
    }

    private function createParserException(TokenStream $tokenStream, string $message): ParserException
    {
        return ParserException::forSnippet($tokenStream->getCodeSnippet(), $message);
    }
}
