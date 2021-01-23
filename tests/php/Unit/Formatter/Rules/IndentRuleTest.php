<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactoryInterface;
use Phel\Formatter\FormatterFactory;
use PHPUnit\Framework\TestCase;

final class IndentRuleTest extends TestCase
{
    use RuleParserTrait;

    private FormatterFactory $formatterFactory;

    public function setUp(): void
    {
        $this->formatterFactory = new FormatterFactory(
            $this->createMock(CompilerFactoryInterface::class)
        );
    }

    public function testListIndention1(): void
    {
        $this->assertIndent(
            [
                '(x a',
                'b',
                'c)',
            ],
            [
                '(x a',
                '   b',
                '   c)',
            ]
        );
    }

    public function testListIndention2(): void
    {
        $this->assertIndent(
            [
                '(x',
                'b',
                'c)',
            ],
            [
                '(x',
                ' b',
                ' c)',
            ]
        );
    }

    public function testBlockIndentionIf(): void
    {
        $this->assertIndent(
            [
                '(if (= x 1)',
                'true',
                'false)',
            ],
            [
                '(if (= x 1)',
                '  true',
                '  false)',
            ]
        );
    }

    public function testBlockIndentionDo(): void
    {
        $this->assertIndent(
            [
                '(do',
                '(foo)',
                '(bar))',
            ],
            [
                '(do',
                '  (foo)',
                '  (bar))',
            ]
        );
    }

    public function testBlockIndentionDo2(): void
    {
        $this->assertIndent(
            [
                '(do (foo)',
                '(bar))',
            ],
            [
                '(do (foo)',
                '    (bar))',
            ]
        );
    }

    public function testBlockIndentionCase(): void
    {
        $this->assertIndent(
            [
                '(case (+ 7 5)',
                '3 :small',
                '12 :big)',
            ],
            [
                '(case (+ 7 5)',
                '  3 :small',
                '  12 :big)',
            ]
        );
    }

    public function testDefIndention(): void
    {
        $this->assertIndent(
            [
                '(def foo',
                '1)',
            ],
            [
                '(def foo',
                '  1)',
            ]
        );
    }

    public function testDefnIndention(): void
    {
        $this->assertIndent(
            [
                '(defn foo [x]',
                '(+ x 1))',
            ],
            [
                '(defn foo [x]',
                '  (+ x 1))',
            ]
        );
    }

    public function testDefnIndention2(): void
    {
        $this->assertIndent(
            [
                '(defn foo',
                '[x]',
                '(+ x 1))',
            ],
            [
                '(defn foo',
                '  [x]',
                '  (+ x 1))',
            ]
        );
    }

    public function testLet(): void
    {
        $this->assertIndent(
            [
                '(let [x 1',
                'y 2]',
                '(+ x 1))',
            ],
            [
                '(let [x 1',
                '      y 2]',
                '  (+ x 1))',
            ]
        );
    }

    public function testTable(): void
    {
        $this->assertIndent(
            [
                '@{:x 1',
                ':y 2}',
            ],
            [
                '@{:x 1',
                '  :y 2}',
            ]
        );
    }

    public function testLetNested(): void
    {
        $this->assertIndent(
            [
                '(let [a @{:x 1',
                ':y 2}]',
                '(:x a))',
            ],
            [
                '(let [a @{:x 1',
                '          :y 2}]',
                '  (:x a))',
            ]
        );
    }

    public function testNestedDef(): void
    {
        $this->assertIndent(
            [
                '(def foo',
                '(fn [bar]',
                '(inc bar)))',
            ],
            [
                '(def foo',
                '  (fn [bar]',
                '    (inc bar)))',
            ]
        );
    }

    private function assertIndent(array $actualLines, array $expectedLines): void
    {
        self::assertEquals(
            $expectedLines,
            explode("\n", $this->indent(implode("\n", $actualLines)))
        );
    }

    private function indent(string $string): string
    {
        return $this->formatterFactory
            ->createIndentRule()
            ->transform($this->parseStringToNode($string))
            ->getCode();
    }
}
