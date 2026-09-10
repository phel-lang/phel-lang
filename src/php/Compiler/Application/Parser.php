<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\ReaderConditionalParser;
use Phel\Compiler\Domain\Parser\ExpressionParserFactoryInterface;
use Phel\Compiler\Domain\Parser\OpenForm;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\AbstractAtomNode;
use Phel\Shared\Parser\Node\CommaNode;
use Phel\Shared\Parser\Node\CommentMacroNode;
use Phel\Shared\Parser\Node\CommentNode;
use Phel\Shared\Parser\Node\FileNode;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\MetaNode;
use Phel\Shared\Parser\Node\NewlineNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\NumberNode;
use Phel\Shared\Parser\Node\QuoteNode;
use Phel\Shared\Parser\Node\ReaderCondSplicingNode;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\TaggedLiteralNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use Phel\Shared\Parser\Node\WhitespaceNode;

use SplStack;
use Throwable;

use function sprintf;
use function str_starts_with;

/**
 * @internal
 */
final readonly class Parser implements ParserInterface
{
    private const string END_OF_FILE_MESSAGE = 'Unexpected end of file: a form was expected.';

    /** @var array<int, true> */
    private const array TOKENS_THAT_SHOULD_STREAM_NEXT = [
        Token::T_WHITESPACE => true,
        Token::T_COMMA => true,
        Token::T_NEWLINE => true,
        Token::T_COMMENT_MACRO => true,
        Token::T_COMMENT => true,
        Token::T_ATOM => true,
        Token::T_STRING => true,
        Token::T_CHAR => true,
        Token::T_REGEX => true,
        Token::T_READER_COND => true,
        Token::T_READER_COND_SPLICING => true,
        Token::T_SYMBOLIC_NUMBER => true,
        Token::T_TAGGED_LITERAL => true,
    ];

    /** @var SplStack<bool> */
    private SplStack $quasiquoteStack;

    /**
     * The delimiters of the forms currently being read, innermost last. A
     * missing closer surfaces somewhere else entirely (at the end of the file,
     * or at the wrong closer), and only this tells the report which line was
     * left open and which bracket is owed.
     *
     * @var SplStack<Token>
     */
    private SplStack $openForms;

    /**
     * The `Parser`-dependent sub-parsers each close over this `Parser`
     * (or its fixed `$globalEnvironment`), both constant for the parser's
     * lifetime, so they are built once here instead of being allocated
     * afresh on every parsed node. The dependency-free sub-parsers
     * (string / char / regex) are memoised on the factory instead.
     */
    private AtomParser $atomParser;

    private ListParser $listParser;

    private QuoteParser $quoteParser;

    private MetaParser $metaParser;

    private ReaderConditionalParser $readerConditionalParser;

    public function __construct(
        private ExpressionParserFactoryInterface $parserFactory,
        GlobalEnvironmentInterface $globalEnvironment,
    ) {
        $this->quasiquoteStack = new SplStack();
        $this->openForms = new SplStack();
        $this->atomParser = $parserFactory->createAtomParser($globalEnvironment);
        $this->listParser = $parserFactory->createListParser($this);
        $this->quoteParser = $parserFactory->createQuoteParser($this);
        $this->metaParser = $parserFactory->createMetaParser($this);
        $this->readerConditionalParser = $parserFactory->createReaderConditionalParser($this);
    }

    /**
     * Reads the next expression from the token stream.
     * If the token stream reaches the end, null is returned.
     *
     * The token stream is lazy, so the lexer runs inside this call and its
     * error surfaces from here.
     *
     * @param TokenStream $tokenStream The token stream to read
     *
     * @throws LexerValueException
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
                Token::T_COMMA => CommaNode::createWithToken($token),
                Token::T_NEWLINE => NewlineNode::createWithToken($token),
                Token::T_COMMENT_MACRO => $this->parseCommentMacroNode($tokenStream, $token),
                Token::T_COMMENT => CommentNode::createWithToken($token),
                Token::T_ATOM => $this->parseAtomNode($token, $tokenStream),
                Token::T_STRING => $this->parseStringNode($token, $tokenStream),
                Token::T_CHAR => $this->parseCharNode($token),
                Token::T_REGEX => $this->parseRegexNode($token),
                Token::T_HASH_FN,
                Token::T_OPEN_PARENTHESIS => $this->parseListNode($token, $tokenStream, Token::T_CLOSE_PARENTHESIS),
                Token::T_OPEN_BRACKET => $this->parseListNode($token, $tokenStream, Token::T_CLOSE_BRACKET),
                Token::T_OPEN_BRACE,
                Token::T_HASH_OPEN_BRACE => $this->parseListNode($token, $tokenStream, Token::T_CLOSE_BRACE),
                Token::T_CLOSE_PARENTHESIS,
                Token::T_CLOSE_BRACKET,
                Token::T_CLOSE_BRACE => throw $this->createUnexpectedCloserException($tokenStream, $token),
                Token::T_QUOTE,
                Token::T_DEREF,
                Token::T_VAR_QUOTE => $this->parseQuoteNode($token, $tokenStream),
                Token::T_CARET => $this->parseMetaNode($tokenStream),
                Token::T_READER_COND => $this->parseReaderCondNode($tokenStream, $token),
                Token::T_READER_COND_SPLICING => $this->parseReaderCondSplicingNode($tokenStream, $token),
                Token::T_SYMBOLIC_NUMBER => $this->parseSymbolicNumberNode($token),
                Token::T_TAGGED_LITERAL => $this->parseTaggedLiteralNode($tokenStream, $token),
                Token::T_EOF => throw $this->createEndOfFileException($tokenStream, $token),
                default => throw $this->createUnexpectedParserException($tokenStream, $token, 'Unhandled syntax token: ' . $token->getCode()),
            };
        }

        // A stream that stops without the terminating T_EOF the lexer appends
        // leaves no token to anchor on, so the open form is the only location
        // left to report.
        $openForm = $this->innermostOpenForm();
        $snippet = $tokenStream->getCodeSnippet();

        if (!$openForm instanceof OpenForm) {
            throw new UnfinishedParserException(
                self::END_OF_FILE_MESSAGE,
                $snippet,
                $snippet->getStartLocation(),
                $snippet->getEndLocation(),
            );
        }

        throw $this->createUnterminatedFormException($tokenStream, $openForm);
    }

    private function shouldTokenStreamGoNext(int $tokenType): bool
    {
        return isset(self::TOKENS_THAT_SHOULD_STREAM_NEXT[$tokenType]);
    }

    private function canParseToken(TokenStream $tokenStream): bool
    {
        return $tokenStream->valid()
            && $tokenStream->current()->getType() !== Token::T_EOF;
    }

    /**
     * @return AbstractAtomNode<mixed>
     */
    private function parseAtomNode(Token $token, TokenStream $tokenStream): AbstractAtomNode
    {
        // The lexer's atom rule excludes only bracket and hash bytes, so an
        // unclosed `"` falls through to it instead of failing to lex. No valid
        // Phel atom starts with a quote, which makes the leading `"` exact.
        if (str_starts_with($token->getCode(), '"')) {
            throw $this->createUnexpectedParserException(
                $tokenStream,
                $token,
                sprintf(
                    'Unterminated string starting at line %d. Did you forget a closing \'"\'?',
                    $token->getStartLocation()->getLine(),
                ),
                ErrorCode::UNTERMINATED_STRING,
            );
        }

        try {
            return $this->atomParser->parse($token);
        } catch (KeywordParserException $keywordParserException) {
            throw $this->createUnexpectedParserException(
                $tokenStream,
                $token,
                $keywordParserException->getMessage(),
                null,
                $keywordParserException,
            );
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
            throw $this->createUnexpectedParserException(
                $tokenStream,
                $token,
                $stringParserException->getMessage(),
                null,
                $stringParserException,
            );
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

    private function createUnexpectedParserException(
        TokenStream $tokenStream,
        Token $currentToken,
        string $message,
        ?ErrorCode $errorCode = null,
        ?Throwable $nestedException = null,
    ): UnexpectedParserException {
        return UnexpectedParserException::forSnippet(
            $tokenStream->getCodeSnippet(),
            $currentToken,
            $message,
            $errorCode,
            $nestedException,
        );
    }

    /**
     * A closer reaches here only when the innermost open form did not consume
     * it, which leaves three different faults to tell apart.
     */
    private function createUnexpectedCloserException(TokenStream $tokenStream, Token $closerToken): UnexpectedParserException
    {
        $openForm = $this->innermostOpenForm();
        $closerText = $closerToken->getCode();

        if (!$openForm instanceof OpenForm) {
            return $this->createUnexpectedParserException(
                $tokenStream,
                $closerToken,
                sprintf("Unexpected '%s': there is no open form to close.", $closerText),
                ErrorCode::UNEXPECTED_TOKEN,
            );
        }

        // The closer matches the open form, so a reader prefix (`'`, `^`, `#_`,
        // `#tag`) swallowed it while still waiting for its own form.
        if ($openForm->getCloserText() === $closerText) {
            return $this->createUnexpectedParserException(
                $tokenStream,
                $closerToken,
                sprintf("Expected a form before '%s'.", $closerText),
                ErrorCode::UNEXPECTED_TOKEN,
            );
        }

        return $this->createUnexpectedParserException(
            $tokenStream,
            $closerToken,
            $openForm->mismatchedCloserMessage($closerText),
            $openForm->getErrorCode(),
        );
    }

    private function createEndOfFileException(TokenStream $tokenStream, Token $eofToken): UnfinishedParserException
    {
        $openForm = $this->innermostOpenForm();

        if (!$openForm instanceof OpenForm) {
            return UnfinishedParserException::forSnippet(
                $tokenStream->getCodeSnippet(),
                $eofToken,
                self::END_OF_FILE_MESSAGE,
                ErrorCode::UNEXPECTED_TOKEN,
            );
        }

        return $this->createUnterminatedFormException($tokenStream, $openForm);
    }

    private function createUnterminatedFormException(TokenStream $tokenStream, OpenForm $openForm): UnfinishedParserException
    {
        return UnfinishedParserException::forSnippet(
            $tokenStream->getCodeSnippet(),
            $openForm->getOpenToken(),
            $openForm->unterminatedMessage(),
            $openForm->getErrorCode(),
        );
    }

    private function innermostOpenForm(): ?OpenForm
    {
        if ($this->openForms->isEmpty()) {
            return null;
        }

        return new OpenForm($this->openForms->top());
    }

    /**
     * @throws UnfinishedParserException
     */
    private function parseListNode(Token $openToken, TokenStream $tokenStream, int $endTokenType): ListNode
    {
        $this->openForms->push($openToken);

        try {
            return $this->listParser->parse($tokenStream, $endTokenType, $openToken->getType());
        } finally {
            $this->openForms->pop();
        }
    }

    private function parseQuoteNode(Token $token, TokenStream $tokenStream): QuoteNode
    {
        return $this->quoteParser
            ->parse($tokenStream, $token->getType());
    }

    private function parseReaderCondNode(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        return $this->readerConditionalParser
            ->parseCond($tokenStream, $openToken);
    }

    private function parseReaderCondSplicingNode(TokenStream $tokenStream, Token $openToken): NodeInterface
    {
        return $this->readerConditionalParser
            ->parseCondSplicing($tokenStream, $openToken);
    }

    private function parseCommaNode(TokenStream $tokenStream): CommaNode
    {
        $token = $tokenStream->current();
        $tokenStream->next();

        return CommaNode::createWithToken($token);
    }

    private function parseMetaNode(TokenStream $tokenStream): MetaNode
    {
        return $this->metaParser
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
