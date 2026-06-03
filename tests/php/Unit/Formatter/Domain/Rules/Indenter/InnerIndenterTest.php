<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Indenter\InnerIndenter;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;

final class InnerIndenterTest extends IndenterTestCase
{
    public function test_it_returns_null_when_the_head_symbol_does_not_match(): void
    {
        // (foo\n bar) with an InnerIndenter for `defn` must not match.
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->newline(),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new InnerIndenter('defn', 0)->getMargin($loc, 2);

        self::assertNull($margin);
    }

    public function test_it_returns_null_when_the_head_is_not_a_symbol(): void
    {
        // (:foo\n bar) — keyword head means formSymbol() is null, no match.
        $list = $this->listNode([
            $this->keyword('foo'),
            $this->newline(),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new InnerIndenter('foo', 0)->getMargin($loc, 2);

        self::assertNull($margin);
    }

    public function test_it_indents_one_level_past_the_parent_line_when_matching(): void
    {
        // (defn\n bar) with depth 0: parent line margin (0 for the root list)
        // plus the indent width (2).
        $list = $this->listNode([
            $this->symbol('defn'),
            $this->newline(),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new InnerIndenter('defn', 0)->getMargin($loc, 2);

        self::assertSame(2, $margin);
    }

    public function test_it_throws_when_depth_exceeds_the_available_ancestry(): void
    {
        // depth 2 tries to climb two levels up from a node with a single
        // ancestor (the root list), walking above the root.
        $this->expectException(ZipperException::class);

        $list = $this->listNode([
            $this->symbol('defn'),
            $this->newline(),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        new InnerIndenter('defn', 2)->getMargin($loc, 2);
    }
}
