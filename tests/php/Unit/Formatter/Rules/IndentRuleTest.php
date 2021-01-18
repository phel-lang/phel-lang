<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Rules\IndentRule;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class IndentRuleTest extends TestCase
{
    public function testListIndention1()
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

    public function testListIndention2()
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

    public function testBlockIndentionIf()
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

    public function testBlockIndentionDo()
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

    public function testBlockIndentionDo2()
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

    public function testBlockIndentionCase()
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

    public function testDefIndention()
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

    public function testDefnIndention()
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

    public function testDefnIndention2()
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

    public function testLet()
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

    public function testTable()
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

    public function testLetNested()
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

    public function testNestedDef()
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

    private function assertIndent(array $actualLines, array $expectedLines)
    {
        $this->assertEquals(
            $expectedLines,
            explode("\n", $this->indent(implode("\n", $actualLines)))
        );
    }

    private function parse(string $string): NodeInterface
    {
        Symbol::resetGen();
        $parser = (new CompilerFactory())->createParser();
        $tokenStream = (new Lexer())->lexString($string);

        return $parser->parseAll($tokenStream);
    }

    private function indent(string $string)
    {
        $node = $this->parse($string);
        $rule = new IndentRule();
        $transformedNode = $rule->transform($node);

        return $transformedNode->getCode();
    }
}
