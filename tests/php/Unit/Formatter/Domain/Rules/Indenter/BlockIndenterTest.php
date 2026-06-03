<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Indenter\BlockIndenter;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;

final class BlockIndenterTest extends IndenterTestCase
{
    public function test_it_returns_null_when_the_head_symbol_does_not_match(): void
    {
        // (foo\n bar) with a BlockIndenter for `if` must not match.
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->newline(),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new BlockIndenter('if', 1)->getMargin($loc, 2);

        self::assertNull($margin);
    }

    public function test_it_returns_null_when_the_head_is_not_a_symbol(): void
    {
        // (:foo\n bar) — the head is a keyword, so formSymbol() is null and no
        // BlockIndenter can match it.
        $list = $this->listNode([
            $this->keyword('foo'),
            $this->newline(),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new BlockIndenter('foo', 1)->getMargin($loc, 2);

        self::assertNull($margin);
    }

    public function test_it_indents_the_block_body_when_the_head_symbol_matches(): void
    {
        // (if\n bar) with index 1: the argument after the index is missing
        // (nthForm out of bounds => null), so it falls back to the InnerIndenter
        // path and produces a positive block indent.
        $list = $this->listNode([
            $this->symbol('if'),
            $this->newline(),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new BlockIndenter('if', 1)->getMargin($loc, 2);

        // InnerIndenter('if', 0): parent line margin (0 for the root list) plus
        // the indent width (2).
        self::assertSame(2, $margin);
    }
}
