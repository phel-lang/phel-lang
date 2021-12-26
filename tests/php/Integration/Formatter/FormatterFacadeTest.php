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
        $formatted = $this->formatterFacade->format(implode("\n", $actualLines));

        self::assertEquals($expectedLines, explode("\n", $formatted));
    }

    public function providerIndent(): Generator
    {
        yield 'ListIndention1' => [
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

        yield 'ListIndention2' => [
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

        yield 'BlockIndentionIf' => [
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

        yield 'BlockIndentionDo' => [
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

        yield 'BlockIndentionDo2' => [
            [
                '(do (foo)',
                '(bar))',
            ],
            [
                '(do (foo)',
                '    (bar))',
            ],
        ];

        yield 'BlockIndentionCase' => [
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

        yield 'DefIndention' => [
            [
                '(def foo',
                '1)',
            ],
            [
                '(def foo',
                '  1)',
            ],
        ];

        yield 'Indent' => [
            [
                '(def foo',
                '1)',
            ],
            [
                '(def foo',
                '  1)',
            ],
        ];

        yield 'DefnIndention1' => [
            [
                '(defn foo [x]',
                '(+ x 1))',
            ],
            [
                '(defn foo [x]',
                '  (+ x 1))',
            ],
        ];

        yield 'DefnIndention2' => [
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

        yield 'Let' => [
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

        yield 'Map' => [
            [
                '{:x 1',
                ':y 2}',
            ],
            [
                '{:x 1',
                ' :y 2}',
            ],
        ];

        yield 'LetNested' => [
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

        yield 'NestedDef' => [
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
        yield 'list' => [
            'actualLines' => ['( 1 2 3 )'],
            'expectedLines' => ['(1 2 3)'],
        ];

        yield 'Vector' => [
            'actualLines' => ['[ 1 2 3 ]'],
            'expectedLines' => ['[1 2 3]'],
        ];

        yield 'One liner Map' => [
            'actualLines' => ['{ :a 1 :b 2 }'],
            'expectedLines' => ['{:a 1 :b 2}'],
        ];

        yield 'RemoveNewlines 1' => [
            'actualLines' => [
                '(',
                'foo',
                ')',
            ],
            'expectedLines' => ['(foo)'],
        ];

        yield 'RemoveNewlines 2' => [
            'actualLines' => [
                '(',
                '  foo',
                ')',
            ],
            'expectedLines' => ['(foo)'],
        ];

        yield 'RemoveNewlines 3' => [
            'actualLines' => [
                '(foo ',
                ')',
            ],
            'expectedLines' => ['(foo)'],
        ];

        yield 'RemoveNewlines 4' => [
            'actualLines' => [
                '(foo',
                '  )',
            ],
            'expectedLines' => ['(foo)'],
        ];
    }

    public function providerRemoveTrailingWhitespaceRule(): Generator
    {
        yield 'Reformatted' => [
            ['  '],
            [''],
        ];

        yield 'AfterList' => [
            ['(foo bar) '],
            ['(foo bar)'],
        ];

        yield 'BetweenList' => [
            [
                '(foo bar) ',
                '(foo bar)',
            ],
            [
                '(foo bar)',
                '(foo bar)',
            ],
        ];

        yield 'InsideList' => [
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
