<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ParserNode;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Lexer\Token;
use Phel\Transpiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Transpiler\Domain\Parser\ParserNode\SymbolNode;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuoteNodeTest extends TestCase
{
    public function test_get_code_quote(): void
    {
        self::assertSame(
            "'a",
            (new QuoteNode(
                Token::T_QUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ))->getCode(),
        );
    }

    public function test_get_code_unquote(): void
    {
        self::assertSame(
            ',a',
            (new QuoteNode(
                Token::T_UNQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ))->getCode(),
        );
    }

    public function test_get_code_unquote_splicing(): void
    {
        self::assertSame(
            ',@a',
            (new QuoteNode(
                Token::T_UNQUOTE_SPLICING,
                $this->loc(1, 0),
                $this->loc(1, 3),
                new SymbolNode('a', $this->loc(1, 2), $this->loc(1, 3), Symbol::create('a')),
            ))->getCode(),
        );
    }

    public function test_get_code_quasiquote(): void
    {
        self::assertSame(
            '`a',
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ))->getCode(),
        );
    }

    public function test_undefined_token(): void
    {
        $this->expectException(RuntimeException::class);
        (new QuoteNode(
            3000,
            $this->loc(1, 0),
            $this->loc(1, 2),
            new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
        ))->getCode();
    }

    public function test_get_children(): void
    {
        self::assertEquals(
            [new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))],
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ))->getChildren(),
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ))->getStartLocation(),
        );
    }

    public function test_get_end_location(): void
    {
        self::assertEquals(
            $this->loc(1, 2),
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            ))->getEndLocation(),
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
