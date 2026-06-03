<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Indenter\ListIndenter;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;

final class ListIndenterTest extends IndenterTestCase
{
    public function test_it_aligns_under_the_head_when_only_one_value_precedes(): void
    {
        // (foo bar) at `bar`: indexOf == 1, so the margin aligns under the head
        // `foo`, i.e. the prior line "(" => margin 1.
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->whitespace(' '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right();

        $margin = new ListIndenter()->getMargin($loc, 2);

        self::assertSame(1, $margin);
    }

    public function test_it_aligns_under_the_first_argument_when_more_than_one_value_precedes(): void
    {
        // (foo bar baz) at `baz`: indexOf == 2 (> 1), so it aligns under the
        // first argument `bar`, whose prior line is "(foo " => margin 5.
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->whitespace(' '),
            $this->symbol('bar'),
            $this->whitespace(' '),
            $this->symbol('baz'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right()->right();

        $margin = new ListIndenter()->getMargin($loc, 2);

        self::assertSame(5, $margin);
    }

    public function test_it_ignores_trivia_siblings_when_counting_preceding_values(): void
    {
        // (foo<ws><ws>bar) at `bar`: the two whitespace lefts are trivia and do
        // not count, so indexOf == 1 and it aligns under the head => margin 1.
        $list = $this->listNode([
            $this->symbol('foo'),
            $this->whitespace(' '),
            $this->whitespace('  '),
            $this->symbol('bar'),
        ]);
        $loc = ParseTreeZipper::createRoot($list)->down()->right()->right()->right();

        $margin = new ListIndenter()->getMargin($loc, 2);

        self::assertSame(1, $margin);
    }
}
