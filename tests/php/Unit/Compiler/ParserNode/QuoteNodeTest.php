<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Lang\SourceLocation;
use Phel\Compiler\ParserNode\QuoteNode;
use Phel\Compiler\ParserNode\SymbolNode;
use Phel\Compiler\Token;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuoteNodeTest extends TestCase
{
    public function testGetCodeQuote()
    {
        self::assertEquals(
            "'a",
            (new QuoteNode(
                Token::T_QUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ))->getCode()
        );
    }

    public function testGetCodeUnquote()
    {
        self::assertEquals(
            ',a',
            (new QuoteNode(
                Token::T_UNQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ))->getCode()
        );
    }

    public function testGetCodeUnquoteSplicing()
    {
        self::assertEquals(
            ',@a',
            (new QuoteNode(
                Token::T_UNQUOTE_SPLICING,
                $this->loc(1, 0),
                $this->loc(1, 3),
                new SymbolNode('a', $this->loc(1, 2), $this->loc(1, 3), Symbol::create('a'))
            ))->getCode()
        );
    }

    public function testGetCodeQuasiquote()
    {
        self::assertEquals(
            '`a',
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ))->getCode()
        );
    }



    public function testUndefinedToken()
    {
        $this->expectException(RuntimeException::class);
        (new QuoteNode(
            3000,
            $this->loc(1, 0),
            $this->loc(1, 2),
            new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
        ))->getCode();
    }

    public function testGetChildren()
    {
        self::assertEquals(
            [new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))],
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ))->getChildren()
        );
    }

    public function testGetStartLocation()
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ))->getStartLocation()
        );
    }

    public function testGetEndLocation()
    {
        self::assertEquals(
            $this->loc(1, 2),
            (new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))
            ))->getEndLocation()
        );
    }

    private function loc($line, $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
