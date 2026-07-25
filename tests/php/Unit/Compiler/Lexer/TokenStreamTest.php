<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Lexer;

use Generator;
use Iterator;
use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Domain\Lexer\TokenStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `getCodeSnippet()` runs while another error is already being reported, so it
 * must never raise one of its own. These cases used to index `$tokens[0]` on an
 * empty array and die with "Call to a member function getStartLocation() on
 * null", replacing the real diagnostic with a PHP `Error`.
 */
final class TokenStreamTest extends TestCase
{
    /**
     * @return Iterator<int<0, max>, array{string, string}>
     */
    public static function provideTriviaOnlySource(): Iterator
    {
        yield 'whitespace only' => ['   ', '   '];
        yield 'newline only' => ["\n", "\n"];
        yield 'comment only' => [';; just a comment', ';; just a comment'];
        yield 'discard only' => ['#_', '#_'];
    }

    #[DataProvider('provideTriviaOnlySource')]
    public function test_code_snippet_of_a_trivia_only_stream_degrades_to_the_trivia(string $source, string $expectedCode): void
    {
        $snippet = new Lexer()->lexString($source, 'test.phel')->getCodeSnippet();

        self::assertSame($expectedCode, $snippet->getCode());
        self::assertSame('test.phel', $snippet->getStartLocation()->getFile());
        self::assertSame(1, $snippet->getStartLocation()->getLine());
    }

    public function test_code_snippet_of_an_exhausted_stream_degrades_to_an_unknown_location(): void
    {
        $tokenStream = new Lexer()->lexString('(def x 1)', 'test.phel');
        while ($tokenStream->valid()) {
            $tokenStream->next();
        }

        $tokenStream->clearReadTokens();
        $snippet = $tokenStream->getCodeSnippet();

        self::assertSame('', $snippet->getCode());
        self::assertSame('', $snippet->getStartLocation()->getFile());
        self::assertSame(0, $snippet->getStartLocation()->getLine());
        self::assertSame(0, $snippet->getEndLocation()->getColumn());
    }

    public function test_code_snippet_of_a_stream_over_an_empty_generator_does_not_throw(): void
    {
        $emptyGenerator = (static function (): Generator {
            yield from [];
        })();

        $snippet = new TokenStream($emptyGenerator)->getCodeSnippet();

        self::assertSame('', $snippet->getCode());
        self::assertSame('', $snippet->getStartLocation()->getFile());
    }
}
