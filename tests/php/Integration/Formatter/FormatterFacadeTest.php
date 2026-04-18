<?php

declare(strict_types=1);

namespace PhelTest\Integration\Formatter;

use Generator;
use Phel\Formatter\FormatterFactory;
use Phel\Lang\Symbol;
use Phel\Phel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatterFacadeTest extends TestCase
{
    private FormatterFactory $formatterFactory;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $this->formatterFactory = new FormatterFactory();
    }

    #[DataProvider('providerIndent')]
    #[DataProvider('providerReformatted')]
    #[DataProvider('providerRemoveTrailingWhitespaceRule')]
    public function test_format(array $actualLines, array $expectedLines): void
    {
        $formatted = $this->formatterFactory
            ->createFormatter()
            ->format(implode("\n", $actualLines));

        self::assertEquals($expectedLines, explode("\n", $formatted));
    }

    public static function providerIndent(): Generator
    {
        yield 'Indent list with two variables' => [
            [
                '(x a',
                'b',
                'c)',
            ],
            [
                '(x a',
                '   b',
                '   c)',
            ],
        ];

        yield 'Indent list with one variable' => [
            [
                '(x',
                'b',
                'c)',
            ],
            [
                '(x',
                ' b',
                ' c)',
            ],
        ];

        yield 'Indent if block' => [
            [
                '(if (= x 1)',
                'true',
                'false)',
            ],
            [
                '(if (= x 1)',
                '  true',
                '  false)',
            ],
        ];

        yield 'Indent do block' => [
            [
                '(do',
                '(foo)',
                '(bar))',
            ],
            [
                '(do',
                '  (foo)',
                '  (bar))',
            ],
        ];

        yield 'Indent do block 2' => [
            [
                '(do (foo)',
                '(bar))',
            ],
            [
                '(do (foo)',
                '    (bar))',
            ],
        ];

        yield 'Indent case block' => [
            [
                '(case (+ 7 5)',
                '3 :small',
                '12 :big)',
            ],
            [
                '(case (+ 7 5)',
                '  3  :small',
                '  12 :big)',
            ],
        ];

        yield 'Align cond pairs' => [
            [
                '(cond',
                'won? :won',
                '(board-full? b) :draw',
                ':else :playing)',
            ],
            [
                '(cond',
                '  won?            :won',
                '  (board-full? b) :draw',
                '  :else           :playing)',
            ],
        ];

        yield 'Align case pairs with default' => [
            [
                '(case x',
                '1 :one',
                '200 :two-hundred',
                ':other)',
            ],
            [
                '(case x',
                '  1   :one',
                '  200 :two-hundred',
                '  :other)',
            ],
        ];

        yield 'Align let bindings' => [
            [
                '(let [x 1',
                'longer 2',
                'a 3]',
                '(+ x longer a))',
            ],
            [
                '(let [x      1',
                '      longer 2',
                '      a      3]',
                '  (+ x longer a))',
            ],
        ];

        yield 'Single line cond not aligned' => [
            ['(cond a 1 b 2)'],
            ['(cond a 1 b 2)'],
        ];

        yield 'Cond with single pair not aligned' => [
            [
                '(cond',
                'a 1)',
            ],
            [
                '(cond',
                '  a 1)',
            ],
        ];

        yield 'Align condp pairs with default' => [
            [
                '(condp = x',
                '1 :one',
                '200 :two-hundred',
                ':other)',
            ],
            [
                '(condp = x',
                '  1   :one',
                '  200 :two-hundred',
                '  :other)',
            ],
        ];

        yield 'Align cond with long-form key' => [
            [
                '(cond',
                '(< x 10) :small',
                ':else :big)',
            ],
            [
                '(cond',
                '  (< x 10) :small',
                '  :else    :big)',
            ],
        ];

        yield 'Cond odd count not aligned' => [
            [
                '(cond',
                'a 1',
                'b)',
            ],
            [
                '(cond',
                '  a 1',
                '  b)',
            ],
        ];

        yield 'Align when-let bindings' => [
            [
                '(when-let [x 1',
                'longer 2]',
                '(+ x longer))',
            ],
            [
                '(when-let [x      1',
                '           longer 2]',
                '  (+ x longer))',
            ],
        ];

        yield 'Align if-let bindings' => [
            [
                '(if-let [x 1',
                'longer 2]',
                'true',
                'false)',
            ],
            [
                '(if-let [x      1',
                '         longer 2]',
                '  true',
                '  false)',
            ],
        ];

        yield 'Align loop bindings' => [
            [
                '(loop [i 0',
                'total 0]',
                '(recur (inc i) total))',
            ],
            [
                '(loop [i     0',
                '       total 0]',
                '  (recur (inc i) total))',
            ],
        ];

        yield 'For bindings not aligned as pairs' => [
            [
                '(for [x :in xs',
                'y :in ys]',
                'body)',
            ],
            [
                '(for [x :in xs',
                '      y :in ys]',
                '  body)',
            ],
        ];

        yield 'Map literal not aligned' => [
            [
                '{:a 1',
                ':bbbb 2}',
            ],
            [
                '{:a 1',
                ' :bbbb 2}',
            ],
        ];

        yield 'Plain vector not aligned' => [
            [
                '[a 1',
                'bb 22]',
            ],
            [
                '[a 1',
                ' bb 22]',
            ],
        ];

        yield 'Nested cond aligns independently' => [
            [
                '(cond',
                'a :outer-a',
                'bigger :outer-big)',
            ],
            [
                '(cond',
                '  a      :outer-a',
                '  bigger :outer-big)',
            ],
        ];

        yield 'Indent def block' => [
            [
                '(def foo',
                '1)',
            ],
            [
                '(def foo',
                '  1)',
            ],
        ];

        yield 'Indent defn block' => [
            [
                '(defn foo [x]',
                '(+ x 1))',
            ],
            [
                '(defn foo [x]',
                '  (+ x 1))',
            ],
        ];

        yield 'Indent defn block 2' => [
            [
                '(defn foo',
                '[x]',
                '(+ x 1))',
            ],
            [
                '(defn foo',
                '  [x]',
                '  (+ x 1))',
            ],
        ];

        yield 'Indent let block' => [
            [
                '(let [x 1',
                'y 2]',
                '(+ x 1))',
            ],
            [
                '(let [x 1',
                '      y 2]',
                '  (+ x 1))',
            ],
        ];

        yield 'Indent map block' => [
            [
                '{:x 1',
                ':y 2}',
            ],
            [
                '{:x 1',
                ' :y 2}',
            ],
        ];

        yield 'Indent let nested block' => [
            [
                '(let [a {:x 1',
                ':y 2}]',
                '(:x a))',
            ],
            [
                '(let [a {:x 1',
                '         :y 2}]',
                '  (:x a))',
            ],
        ];

        yield 'Indent nested def block' => [
            [
                '(def foo',
                '(fn [bar]',
                '(inc bar)))',
            ],
            [
                '(def foo',
                '  (fn [bar]',
                '    (inc bar)))',
            ],
        ];
    }

    public static function providerReformatted(): Generator
    {
        yield 'One liner List' => [
            ['( 1 2 3 )'],
            ['(1 2 3)'],
        ];

        yield 'One liner Vector' => [
            ['[ 1 2 3 ]'],
            ['[1 2 3]'],
        ];

        yield 'One liner Map' => [
            ['{ :a 1 :b 2 }'],
            ['{:a 1 :b 2}'],
        ];

        yield 'Remove new lines without empty spaces' => [
            [
                '(',
                'foo',
                ')',
            ],
            ['(foo)'],
        ];

        yield 'Remove new lines with empty space in the beginning at the middle string' => [
            [
                '(',
                '  foo',
                ')',
            ],
            ['(foo)'],
        ];

        yield 'Remove new lines with empty space at the end of the first string' => [
            [
                '(foo ',
                ')',
            ],
            ['(foo)'],
        ];

        yield 'Remove new lines with empty space at the beginning of the second string' => [
            [
                '(foo',
                '  )',
            ],
            ['(foo)'],
        ];
    }

    public static function providerRemoveTrailingWhitespaceRule(): Generator
    {
        yield 'Remove basic trailing whitespace' => [
            ['  '],
            [''],
        ];

        yield 'Remove trailing whitespace after list' => [
            ['(foo bar) '],
            ['(foo bar)'],
        ];

        yield 'Remove trailing whitespace between list' => [
            [
                '(foo bar) ',
                '(foo bar)',
            ],
            [
                '(foo bar)',
                '(foo bar)',
            ],
        ];

        yield 'Remove trailing whitespace inside list' => [
            [
                '(a',
                ' ',
                ' x)',
            ],
            [
                '(a',
                '',
                ' x)',
            ],
        ];
    }
}
