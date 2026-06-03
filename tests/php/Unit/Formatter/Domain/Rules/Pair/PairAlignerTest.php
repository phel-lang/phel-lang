<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Domain\Rules\Pair;

use Phel\Formatter\Domain\Rules\Pair\PairAligner;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\CommentNode;
use Phel\Shared\Parser\Node\KeywordNode;
use Phel\Shared\Parser\Node\NewlineNode;
use Phel\Shared\Parser\Node\NumberNode;
use Phel\Shared\Parser\Node\WhitespaceNode;
use PHPUnit\Framework\TestCase;

use function strlen;

final class PairAlignerTest extends TestCase
{
    public function test_it_aligns_values_under_a_common_column(): void
    {
        // {:a 1\n :longkey 2}
        $children = [
            $this->keyword('a'),
            $this->whitespace(' '),
            $this->number('1'),
            $this->newline(),
            $this->whitespace(' '),
            $this->keyword('longkey'),
            $this->whitespace(' '),
            $this->number('2'),
        ];

        $result = new PairAligner()->align($children, 0, false);

        // The whitespace after :a should be padded so that 1 and 2 align under
        // column (strlen(":longkey") + 1).
        $padAfterA = $result[1];
        self::assertInstanceOf(WhitespaceNode::class, $padAfterA);
        self::assertSame(strlen(':longkey') - strlen(':a') + 1, strlen($padAfterA->getCode()));

        // The wider key keeps a single space before its value.
        $padAfterLongkey = $result[6];
        self::assertSame(' ', $padAfterLongkey->getCode());
    }

    public function test_it_returns_children_unmodified_when_fewer_than_two_pairs(): void
    {
        // Only a single pair (2 values) is below the minimum of 4 values.
        $children = [
            $this->keyword('a'),
            $this->whitespace(' '),
            $this->number('1'),
        ];

        $result = new PairAligner()->align($children, 0, false);

        self::assertSame($children, $result);
    }

    public function test_it_returns_children_unmodified_when_pairs_are_on_a_single_line(): void
    {
        // {:a 1 :b 2} with no newline between pairs => no multi-line span.
        $children = [
            $this->keyword('a'),
            $this->whitespace(' '),
            $this->number('1'),
            $this->whitespace(' '),
            $this->keyword('b'),
            $this->whitespace(' '),
            $this->number('2'),
        ];

        $result = new PairAligner()->align($children, 0, false);

        self::assertSame($children, $result);
    }

    public function test_it_returns_children_unmodified_when_odd_count_without_allow_trailing_single(): void
    {
        // Three non-trivia values => odd count, not key/value pairs.
        $children = [
            $this->keyword('a'),
            $this->whitespace(' '),
            $this->number('1'),
            $this->newline(),
            $this->keyword('b'),
        ];

        $result = new PairAligner()->align($children, 0, false);

        self::assertSame($children, $result);
    }

    public function test_it_drops_the_trailing_single_value_when_allowed(): void
    {
        // (cond :a 1\n :longkey 2\n :else) => 5 values, last one dropped, the
        // remaining 4 are aligned.
        $children = [
            $this->keyword('a'),
            $this->whitespace(' '),
            $this->number('1'),
            $this->newline(),
            $this->whitespace(' '),
            $this->keyword('longkey'),
            $this->whitespace(' '),
            $this->number('2'),
            $this->newline(),
            $this->keyword('else'),
        ];

        $result = new PairAligner()->align($children, 0, true);

        $padAfterA = $result[1];
        self::assertInstanceOf(WhitespaceNode::class, $padAfterA);
        self::assertSame(strlen(':longkey') - strlen(':a') + 1, strlen($padAfterA->getCode()));
    }

    public function test_it_skips_leading_values_via_skip_value_count(): void
    {
        // (case x :a 1\n :longkey 2) — skip the first 2 values (the head form
        // is excluded by the caller; here we skip "case" head + "x").
        $children = [
            $this->keyword('head'),
            $this->whitespace(' '),
            $this->number('99'),
            $this->whitespace(' '),
            $this->keyword('a'),
            $this->whitespace(' '),
            $this->number('1'),
            $this->newline(),
            $this->whitespace(' '),
            $this->keyword('longkey'),
            $this->whitespace(' '),
            $this->number('2'),
        ];

        $result = new PairAligner()->align($children, 2, false);

        // The skipped leading whitespace (index 1, 3) must stay untouched.
        self::assertSame(' ', $result[1]->getCode());
        // The whitespace after the :a key (now a real key) must be padded.
        $padAfterA = $result[5];
        self::assertSame(strlen(':longkey') - strlen(':a') + 1, strlen($padAfterA->getCode()));
    }

    public function test_it_does_not_align_a_pair_with_a_comment_gap(): void
    {
        // The :a / 1 pair has a comment between key and value, so it must keep
        // its original whitespace (gap is not horizontal).
        $children = [
            $this->keyword('a'),
            $this->whitespace(' '),
            $this->comment('; note'),
            $this->newline(),
            $this->number('1'),
            $this->newline(),
            $this->keyword('longkey'),
            $this->whitespace(' '),
            $this->number('2'),
        ];

        $result = new PairAligner()->align($children, 0, false);

        // The whitespace right after :a is left as the original single space
        // because the gap contains a comment/newline.
        self::assertSame(' ', $result[1]->getCode());
    }

    public function test_it_returns_children_unmodified_when_a_key_spans_multiple_lines(): void
    {
        // A multi-line key code makes maxKeyWidth() bail out.
        $children = [
            $this->multilineKey(),
            $this->whitespace(' '),
            $this->number('1'),
            $this->newline(),
            $this->keyword('b'),
            $this->whitespace(' '),
            $this->number('2'),
        ];

        $result = new PairAligner()->align($children, 0, false);

        self::assertSame($children, $result);
    }

    private function keyword(string $name): KeywordNode
    {
        return new KeywordNode(':' . $name, $this->loc(), $this->loc(), Keyword::create($name));
    }

    private function multilineKey(): KeywordNode
    {
        // A key whose code contains a newline (synthetic but exercises the guard).
        return new KeywordNode(":a\nb", $this->loc(), $this->loc(), Keyword::create('a'));
    }

    private function number(string $code): NumberNode
    {
        return new NumberNode($code, $this->loc(), $this->loc(), (int) $code);
    }

    private function whitespace(string $code): WhitespaceNode
    {
        return new WhitespaceNode($code, $this->loc(), $this->loc());
    }

    private function newline(): NewlineNode
    {
        return new NewlineNode("\n", $this->loc(), $this->loc());
    }

    private function comment(string $code): CommentNode
    {
        return new CommentNode($code, $this->loc(), $this->loc());
    }

    private function loc(): SourceLocation
    {
        return new SourceLocation('', 0, 0);
    }
}
