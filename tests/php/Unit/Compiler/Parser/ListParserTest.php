<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser;

use Generator;
use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Domain\Parser\ExpressionParserFactory;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\FileNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\NumberNode;
use Phel\Shared\Parser\Node\Token;
use PHPUnit\Framework\TestCase;

final class ListParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $env = new GlobalEnvironment();
        $this->parser = new Parser(new ExpressionParserFactory(), $env);
    }

    public function test_unterminated_list_error_throws_unfinished_exception(): void
    {
        $code = '(def foo
  (fn [x]
    (+ x 1)
';

        $this->expectException(UnfinishedParserException::class);
        $this->expectExceptionMessageMatches('/Unterminated list/');

        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($code, 'test.phel');
        $this->parser->parseAll($tokenStream);
    }

    public function test_unterminated_vector_error_throws_unfinished_exception(): void
    {
        $code = '[1 2 3';

        $this->expectException(UnfinishedParserException::class);
        $this->expectExceptionMessageMatches('/Unterminated list/');

        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($code, 'test.phel');
        $this->parser->parseAll($tokenStream);
    }

    public function test_unterminated_map_error_throws_unfinished_exception(): void
    {
        $code = '{:a 1 :b 2';

        $this->expectException(UnfinishedParserException::class);
        $this->expectExceptionMessageMatches('/Unterminated list/');

        $lexer = new Lexer();
        $tokenStream = $lexer->lexString($code, 'test.phel');
        $this->parser->parseAll($tokenStream);
    }

    /**
     * `ListParser` falls out of its loop when the stream runs dry, and used to
     * ask for `current()` to build the exception. On a dry stream that raises
     * "Token generator exhausted unexpectedly" — the reporter throwing while
     * reporting, which replaces the user's real diagnostic with an internal
     * one.
     *
     * The lexer always terminates a stream with `T_EOF`, and `Parser` turns
     * that into its own `UnfinishedParserException` first, so no `phel` input
     * reaches this today. It is driven directly here because an error reporter
     * that can destroy the error is worth closing whether or not a caller can
     * currently get there.
     */
    public function test_an_exhausted_stream_still_reports_the_unterminated_list(): void
    {
        $stream = new TokenStream($this->tokensWithoutEof());

        try {
            new ListParser($this->parserConsumingOneToken())
                ->parse($stream, Token::T_CLOSE_PARENTHESIS, Token::T_HASH_FN);
            self::fail('Expected an UnfinishedParserException.');
        } catch (UnfinishedParserException $unfinishedParserException) {
            self::assertSame(
                "Unterminated list starting at line 1. Did you forget a closing ')'?",
                $unfinishedParserException->getMessage(),
            );
            self::assertSame(ErrorCode::UNTERMINATED_LIST, $unfinishedParserException->getErrorCode());
            // Anchored on the opening bracket, the line the user has to fix.
            self::assertSame(1, $unfinishedParserException->getStartLocation()->getLine());
        }
    }

    /**
     * A stream that stops without the `T_EOF` the lexer would append.
     *
     * @return Generator<int, Token>
     */
    private function tokensWithoutEof(): Generator
    {
        yield new Token(Token::T_OPEN_PARENTHESIS, '(', new SourceLocation('test.phel', 1, 0), new SourceLocation('test.phel', 1, 1));
        yield new Token(Token::T_ATOM, '1', new SourceLocation('test.phel', 1, 1), new SourceLocation('test.phel', 1, 2));
    }

    /**
     * Stands in for the real parser: consumes the token it is handed and
     * returns a node, so the loop advances to the end of the stream.
     */
    private function parserConsumingOneToken(): ParserInterface
    {
        return new class() implements ParserInterface {
            public function parseNext(TokenStream $tokenStream): NodeInterface
            {
                return $this->readExpression($tokenStream);
            }

            public function parseAll(TokenStream $tokenStream): FileNode
            {
                return new FileNode([$this->readExpression($tokenStream)]);
            }

            public function readExpression(TokenStream $tokenStream): NodeInterface
            {
                $token = $tokenStream->current();
                $tokenStream->next();

                return new NumberNode(
                    $token->getCode(),
                    $token->getStartLocation(),
                    $token->getEndLocation(),
                    1,
                );
            }
        };
    }
}
