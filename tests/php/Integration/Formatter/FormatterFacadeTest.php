<?php

declare(strict_types=1);

namespace PhelTest\Integration\Formatter;

use Generator;
use Phel\Formatter\FormatterFacade;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class FormatterFacadeTest extends TestCase
{
    private FormatterFacade $formatterFacade;

    public function setUp(): void
    {
        Symbol::resetGen();
        $this->formatterFacade = new FormatterFacade();
    }

    /**
     * @dataProvider providerIndent
     * @dataProvider providerReformatted
     * @dataProvider providerRemoveTrailingWhitespaceRule
     */
    public function test_format(array $actualLines, array $expectedLines): void
    {
        $formatted = $this->formatterFacade
            ->getFormatCommand()
            ->getFormatter()
            ->format(implode("\n", $actualLines));

        self::assertEquals($expectedLines, explode("\n", $formatted));
    }

    public function providerIndent(): Generator
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
                '  3 :small',
                '  12 :big)',
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

    public function providerReformatted(): Generator
    {
        yield 'One liner List' => [
            'actualLines' => ['( 1 2 3 )'],
            'expectedLines' => ['(1 2 3)'],
        ];

        yield 'One liner Vector' => [
            'actualLines' => ['[ 1 2 3 ]'],
            'expectedLines' => ['[1 2 3]'],
        ];

        yield 'One liner Map' => [
            'actualLines' => ['{ :a 1 :b 2 }'],
            'expectedLines' => ['{:a 1 :b 2}'],
        ];

        yield 'Remove new lines without empty spaces' => [
            'actualLines' => [
                '(',
                'foo',
                ')',
            ],
            'expectedLines' => ['(foo)'],
        ];

        yield 'Remove new lines with empty space in the beginning at the middle string' => [
            'actualLines' => [
                '(',
                '  foo',
                ')',
            ],
            'expectedLines' => ['(foo)'],
        ];

        yield 'Remove new lines with empty space at the end of the first string' => [
            'actualLines' => [
                '(foo ',
                ')',
            ],
            'expectedLines' => ['(foo)'],
        ];

        yield 'Remove new lines with empty space at the beginning of the second string' => [
            'actualLines' => [
                '(foo',
                '  )',
            ],
            'expectedLines' => ['(foo)'],
        ];
    }

    public function providerRemoveTrailingWhitespaceRule(): Generator
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
