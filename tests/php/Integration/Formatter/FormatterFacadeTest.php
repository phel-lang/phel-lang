<?php

declare(strict_types=1);

namespace PhelTest\Integration\Formatter;

use Gacela\Framework\Gacela;
use Generator;
use Phel\Formatter\FormatterFactory;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatterFacadeTest extends TestCase
{
    private FormatterFactory $formatterFactory;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
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
