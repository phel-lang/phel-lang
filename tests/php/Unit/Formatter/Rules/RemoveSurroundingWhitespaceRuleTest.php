<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Rules\RemoveSurroundingWhitespaceRule;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class RemoveSurroundingWhitespaceRuleTest extends TestCase
{
    public function testList()
    {
        $this->assertReformatted(
            ['( 1 2 3 )'],
            ['(1 2 3)']
        );
    }

    public function testBracketList()
    {
        $this->assertReformatted(
            ['[ 1 2 3 ]'],
            ['[1 2 3]']
        );
    }

    public function testArray()
    {
        $this->assertReformatted(
            ['@[ 1 2 3 ]'],
            ['@[1 2 3]']
        );
    }

    public function testTable()
    {
        $this->assertReformatted(
            ['@{ :a 1 :b 2 }'],
            ['@{:a 1 :b 2}']
        );
    }

    public function testRemoveNewlines()
    {
        $this->assertReformatted(
            [
                '(',
                'foo',
                ')',
            ],
            ['(foo)']
        );

        $this->assertReformatted(
            [
                '(',
                '  foo',
                ')',
            ],
            ['(foo)']
        );

        $this->assertReformatted(
            [
                '(foo ',
                ')',
            ],
            ['(foo)']
        );

        $this->assertReformatted(
            [
                '(foo',
                '  )',
            ],
            ['(foo)']
        );
    }


    private function assertReformatted(array $actualLines, array $expectedLines)
    {
        $this->assertEquals(
            $expectedLines,
            explode("\n", $this->reformat(implode("\n", $actualLines)))
        );
    }

    private function parse(string $string): NodeInterface
    {
        Symbol::resetGen();
        $parser = (new CompilerFactory())->createParser();
        $tokenStream = (new Lexer())->lexString($string);

        return $parser->parseAll($tokenStream);
    }

    private function reformat(string $string)
    {
        $node = $this->parse($string);
        $rule = new RemoveSurroundingWhitespaceRule();
        $transformedNode = $rule->transform($node);

        return $transformedNode->getCode();
    }
}
