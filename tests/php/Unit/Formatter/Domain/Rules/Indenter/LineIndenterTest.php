<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Indenter\LineIndenter;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;

final class LineIndenterTest extends IndenterTestCase
{
    public function test_it_measures_the_prior_line_length_within_a_list(): void
    {
        // (foo bar) — positioned at `bar`, the prior line is "(foo " (5 chars).
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right();

        $margin = new LineIndenter()->getMargin($loc, 2);

        self::assertSame(5, $margin);
    }

    public function test_it_only_counts_the_last_line_after_a_newline(): void
    {
        // (foo\n  bar) — the newline resets the margin so only "  " counts (2).
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->newline(),
            $this->whitespace('  '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new LineIndenter()->getMargin($loc, 2);

        self::assertSame(2, $margin);
    }

    public function test_it_returns_zero_margin_at_the_first_node_of_the_root(): void
    {
        // The head symbol of the root list has no prior content on its line, so
        // the reconstructed prior line is just the list prefix "(" (1 char).
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down();

        $margin = new LineIndenter()->getMargin($loc, 2);

        self::assertSame(1, $margin);
    }
}
