<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Parser\Node;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\QuoteNode;
use Phel\Shared\Parser\Node\SymbolNode;
use Phel\Shared\Parser\Node\Token;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuoteNodeTest extends TestCase
{
    public function test_legacy_serialized_node_has_empty_prefix_trivia(): void
    {
        // Serialized by the node implementation before prefix trivia (903500215).
        $payload = 'H4sIAAAAAAAC/5WRwW6DMAyGeZY8QWmgm8yp9wqYuiOXDFwaLU1QEiamae++GNpNmjoGN0f5P/v37wI4B1aeUVXHs7DYVKWwDm2Vmwarp954pIpBCh8OkgRY9K848uYV9fN7hyyTECdZAB8Xgc4L6w+mFl4azbICtsnV3EHotjqa3tb4/Q8JeeKbW+s7mugkVXDhYAfMeSt1S495REk9GSfldk5ZG9VfNGk3tGM8p8WhE9qFqrCylQHKs8/A7Bblgrr5ScUCp2npMnLoLDp3jZPHf986l2q69JjqrLH9S8hS1H7vzWWcUhNIcQHTUlGVpmv4X3efNkz5mhb3InpY0+BNqB5vZ1kWLq1dWjzJYeS+APviXuRMAwAA';
        $node = unserialize(gzdecode(base64_decode($payload)));

        self::assertInstanceOf(QuoteNode::class, $node);
        self::assertSame("'nil", $node->getCode());
    }

    public function test_get_code_quote(): void
    {
        self::assertSame(
            "'a",
            new QuoteNode(
                Token::T_QUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            )->getCode(),
        );
    }

    public function test_get_code_unquote(): void
    {
        self::assertSame(
            '~a',
            new QuoteNode(
                Token::T_UNQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            )->getCode(),
        );
    }

    public function test_get_code_unquote_splicing(): void
    {
        self::assertSame(
            '~@a',
            new QuoteNode(
                Token::T_UNQUOTE_SPLICING,
                $this->loc(1, 0),
                $this->loc(1, 3),
                new SymbolNode('a', $this->loc(1, 2), $this->loc(1, 3), Symbol::create('a')),
            )->getCode(),
        );
    }

    public function test_get_code_unquote_preserves_tilde_prefix(): void
    {
        self::assertSame(
            '~a',
            new QuoteNode(
                Token::T_UNQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
                '~',
            )->getCode(),
        );
    }

    public function test_get_code_unquote_splicing_preserves_tilde_prefix(): void
    {
        self::assertSame(
            '~@a',
            new QuoteNode(
                Token::T_UNQUOTE_SPLICING,
                $this->loc(1, 0),
                $this->loc(1, 3),
                new SymbolNode('a', $this->loc(1, 2), $this->loc(1, 3), Symbol::create('a')),
                '~@',
            )->getCode(),
        );
    }

    public function test_get_code_quasiquote(): void
    {
        self::assertSame(
            '`a',
            new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            )->getCode(),
        );
    }

    public function test_undefined_token(): void
    {
        $this->expectException(RuntimeException::class);
        new QuoteNode(
            3000,
            $this->loc(1, 0),
            $this->loc(1, 2),
            new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
        )->getCode();
    }

    public function test_get_children(): void
    {
        self::assertEquals(
            [new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a'))],
            new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            )->getChildren(),
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            )->getStartLocation(),
        );
    }

    public function test_get_end_location(): void
    {
        self::assertEquals(
            $this->loc(1, 2),
            new QuoteNode(
                Token::T_QUASIQUOTE,
                $this->loc(1, 0),
                $this->loc(1, 2),
                new SymbolNode('a', $this->loc(1, 1), $this->loc(1, 2), Symbol::create('a')),
            )->getEndLocation(),
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
