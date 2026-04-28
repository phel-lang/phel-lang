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
use Phel\Compiler\Domain\Parser\ParserNode\CommaNode;
use Phel\Compiler\Domain\Parser\ParserNode\CommentMacroNode;
use Phel\Compiler\Domain\Parser\ParserNode\CommentNode;
use Phel\Compiler\Domain\Parser\ParserNode\FileNode;
use Phel\Compiler\Domain\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\MetaNode;
use Phel\Compiler\Domain\Parser\ParserNode\NewlineNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\NumberNode;
use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Domain\Parser\ParserNode\ReaderCondSplicingNode;
use Phel\Compiler\Domain\Parser\ParserNode\StringNode;
use Phel\Compiler\Domain\Parser\ParserNode\TaggedLiteralNode;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\WhitespaceNode;

use SplStack;

use function in_array;

final readonly class Parser implements ParserInterface
{
    private const array TOKENS_THAT_SHOULD_STREAM_NEXT = [
        Token::T_WHITESPACE,
        Token::T_NEWLINE,
        Token::T_COMMENT_MACRO,
        Token::T_COMMENT,
        Token::T_ATOM,
        Token::T_STRING,
        Token::T_CHAR,
        Token::T_REGEX,
        Token::T_READER_COND,
        Token::T_READER_COND_SPLICING,
        Token::T_SYMBOLIC_NUMBER,
        Token::T_TAGGED_LITERAL,
    ];

    private SplStack $quasiquoteStack;

    public function __construct(
        private ExpressionParserFactoryInterface $parserFactory,
        private GlobalEnvironmentInterface $globalEnvironment,
    ) {
        $this->quasiquoteStack = new SplStack();
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

        $node = $this->readExpression($tokenStream);

        if ($node instanceof ReaderCondSplicingNode) {
            throw new UnexpectedParserException(
                'Reader conditional splicing #?@() is not allowed at the top level',
                $tokenStream->getCodeSnippet(),
                $node->getStartLocation(),
                $node->getEndLocation(),
            );
        }

        return $node;
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

            if ($tokenType === Token::T_QUASIQUOTE) {
                $this->enterQuasiquote();
                $node = $this->parseQuoteNode($token, $tokenStream);
                $this->leaveQuasiquote();

                return $node;
            }

            if ($tokenType === Token::T_UNQUOTE || $tokenType === Token::T_UNQUOTE_SPLICING) {
                if (!$this->isInsideQuasiquote()) {
                    return $this->parseCommaNode($tokenStream);
                }

                $this->leaveQuasiquote();
                $node = $this->parseQuoteNode($token, $tokenStream);
                $this->enterQuasiquote();

                return $node;
            }

            if ($this->shouldTokenStreamGoNext($tokenType)) {
                $tokenStream->next();
            }

            return match ($tokenType) {
                Token::T_WHITESPACE => WhitespaceNode::createWithToken($token),
                Token::T_NEWLINE => NewlineNode::createWithToken($token),
                Token::T_COMMENT_MACRO => $this->parseCommentMacroNode($tokenStream, $token),
                Token::T_COMMENT => CommentNode::createWithToken($token),
                Token::T_ATOM => $this->parseAtomNode($token, $tokenStream),
                Token::T_STRING => $this->parseStringNode($token, $tokenStream),
                Token::T_CHAR => $this->parseCharNode($token),
                Token::T_REGEX => $this->parseRegexNode($token),
                Token::T_HASH_FN,
                Token::T_FN,
                Token::T_OPEN_PARENTHESIS => $this->parseFnListNode($token, $tokenStream),
                Token::T_OPEN_BRACKET => $this->parseArrayListNode($token, $tokenStream),
                Token::T_OPEN_BRACE => $this->parseMapListNode($token, $tokenStream),
                Token::T_HASH_OPEN_BRACE => $this->parseSetListNode($token, $tokenStream),
                Token::T_CLOSE_PARENTHESIS,
                Token::T_CLOSE_BRACKET,
                Token::T_CLOSE_BRACE => throw $this->createUnexceptedParserException($tokenStream, $token, 'Unterminated list (BRACKETS)'),
                Token::T_QUOTE,
                Token::T_DEREF,
                Token::T_VAR_QUOTE => $this->parseQuoteNode($token, $tokenStream),
                Token::T_CARET => $this->parseMetaNode($tokenStream),
                Token::T_READER_COND => $this->parseReaderCondNode($tokenStream, $token),
                Token::T_READER_COND_SPLICING => $this->parseReaderCondSplicingNode($tokenStream, $token),
                Token::T_SYMBOLIC_NUMBER => $this->parseSymbolicNumberNode($token),
                Token::T_TAGGED_LITERAL => $this->parseTaggedLiteralNode($tokenStream, $token),
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

    private function parseCommentMacroNode(TokenStream $tokenStream, Token $token): CommentMacroNode
    {
        do {
            $ignored = $this->readExpression($tokenStream);
        } while ($ignored instanceof TriviaNodeInterface);

        return new CommentMacroNode($ignored, $token->getStartLocation());
    }

    /**
     * Produces a NumberNode with value PHP INF/-INF/NAN from a symbolic
     * number literal token (`##Inf`, `##-Inf`, `##NaN`). The lexer only
     * emits these three exact forms, so no other code paths are reachable.
     */
    private function parseSymbolicNumberNode(Token $token): NumberNode
    {
        $value = match ($token->getCode()) {
            '##-Inf' => -INF,
            '##NaN' => NAN,
            default => INF, // '##Inf'
        };

        return new NumberNode(
            $token->getCode(),
            $token->getStartLocation(),
            $token->getEndLocation(),
            $value,
        );
    }

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    private function parseTaggedLiteralNode(TokenStream $tokenStream, Token $token): TaggedLiteralNode
    {
        // Tag name is everything after the leading '#'
        $tag = substr($token->getCode(), 1);

        // The stream is already past the tag token at this point because
        // T_TAGGED_LITERAL is in TOKENS_THAT_SHOULD_STREAM_NEXT.
        // Read the next non-trivia form as the tagged value.
        do {
            $form = $this->readExpression($tokenStream);
        } while ($form instanceof TriviaNodeInterface);

        return new TaggedLiteralNode(
            $tag,
            $form,
            $token->getStartLocation(),
            $form->getEndLocation(),
        );
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

    private function parseCharNode(Token $token): StringNode
    {
        return $this->parserFactory
            ->createCharParser()
            ->parse($token);
    }

    private function parseRegexNode(Token $token): StringNode
    {
        return $this->parserFactory
            ->createRegexParser()
            ->parse($token);
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

    private function parseSetListNode(Token $token, TokenStream $tokenStream): ListNode
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

    /**
     * Reads the next non-trivia expression inside a reader conditional.
     * Returns null when only trivia remains before the closing paren (or EOF),
     * letting the caller exit the branch loop cleanly.
     */
    private function readNonTriviaExpression(TokenStream $tokenStream): ?NodeInterface
    {
        while ($tokenStream->valid()) {
            if ($tokenStream->current()->getType() === Token::T_CLOSE_PARENTHESIS) {
                return null;
            }

            $node = $this->readExpression($tokenStream);
            if (!$node instanceof TriviaNodeInterface) {
                return $node;
            }
        }

        return null;
    }

    private function parseReaderCondNode(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        $phelNode = null;
        $defaultNode = null;

        while ($tokenStream->valid()) {
            $keywordNode = $this->readNonTriviaExpression($tokenStream);
            if (!$keywordNode instanceof NodeInterface) {
                break;
            }

            $formNode = $this->readNonTriviaExpression($tokenStream);
            if (!$formNode instanceof NodeInterface) {
                break;
            }

            // Check keyword
            if ($keywordNode instanceof KeywordNode) {
                $keywordCode = $keywordNode->getCode();
                if ($keywordCode === ':phel') {
                    $phelNode = $formNode;
                } elseif ($keywordCode === ':default') {
                    $defaultNode = $formNode;
                }
            }
        }

        // Consume the closing paren — throw if the stream is exhausted or missing ')'
        if (!$tokenStream->valid() || $tokenStream->current()->getType() !== Token::T_CLOSE_PARENTHESIS) {
            throw $this->createUnfinishedParserException($tokenStream, $openToken, 'Unterminated reader conditional #?()');
        }

        $tokenStream->next();

        $matchedNode = $phelNode ?? $defaultNode;
        if ($matchedNode instanceof NodeInterface) {
            return $matchedNode;
        }

        // No matching branch → treated as trivia (dropped)
        return CommentNode::createWithToken($openToken);
    }

    private function parseReaderCondSplicingNode(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        $phelNode = null;
        $defaultNode = null;

        while ($tokenStream->valid()) {
            $keywordNode = $this->readNonTriviaExpression($tokenStream);
            if (!$keywordNode instanceof NodeInterface) {
                break;
            }

            $formNode = $this->readNonTriviaExpression($tokenStream);
            if (!$formNode instanceof NodeInterface) {
                break;
            }

            // Check keyword
            if ($keywordNode instanceof KeywordNode) {
                $keywordCode = $keywordNode->getCode();
                if ($keywordCode === ':phel') {
                    $phelNode = $formNode;
                } elseif ($keywordCode === ':default') {
                    $defaultNode = $formNode;
                }
            }
        }

        // Consume the closing paren
        if (!$tokenStream->valid() || $tokenStream->current()->getType() !== Token::T_CLOSE_PARENTHESIS) {
            throw $this->createUnfinishedParserException($tokenStream, $openToken, 'Unterminated reader conditional splicing #?@()');
        }

        $tokenStream->next();

        $matchedNode = $phelNode ?? $defaultNode;
        if ($matchedNode instanceof ListNode) {
            return new ReaderCondSplicingNode(
                $matchedNode->getChildren(),
                $openToken->getStartLocation(),
                $matchedNode->getEndLocation(),
            );
        }

        if ($matchedNode instanceof NodeInterface) {
            throw $this->createUnexceptedParserException(
                $tokenStream,
                $openToken,
                'Reader conditional splicing #?@() requires a sequential collection (list or vector), got: ' . $matchedNode->getCode(),
            );
        }

        // No matching branch → splice nothing
        return new ReaderCondSplicingNode(
            [],
            $openToken->getStartLocation(),
            $openToken->getEndLocation(),
        );
    }

    private function parseCommaNode(TokenStream $tokenStream): CommaNode
    {
        $token = $tokenStream->current();
        $tokenStream->next();

        return CommaNode::createWithToken($token);
    }

    private function parseMetaNode(TokenStream $tokenStream): MetaNode
    {
        return $this->parserFactory
            ->createMetaParser($this)
            ->parse($tokenStream);
    }

    private function isInsideQuasiquote(): bool
    {
        return !$this->quasiquoteStack->isEmpty();
    }

    private function enterQuasiquote(): void
    {
        $this->quasiquoteStack->push(true);
    }

    private function leaveQuasiquote(): void
    {
        if (!$this->quasiquoteStack->isEmpty()) {
            $this->quasiquoteStack->pop();
        }
    }
}
