<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Rules\RemoveTrailingWhitespaceRule;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class RemoveTrailingWhitespaceRuleTest extends TestCase
{
    public function testEmptyLine()
    {
        $this->assertReformatted(
            ['  '],
            ['']
        );
    }

    public function testAfterList()
    {
        $this->assertReformatted(
            ['(foo bar) '],
            ['(foo bar)']
        );
    }

    public function testBetweenList()
    {
        $this->assertReformatted(
            [
                '(foo bar) ',
                '(foo bar)',
            ],
            [
                '(foo bar)',
                '(foo bar)',
            ]
        );
    }

    public function testInsideList()
    {
        $this->assertReformatted(
            [
                '(a',
                ' ',
                ' x)',
            ],
            [
                '(a',
                '',
                ' x)',
            ]
        );
    }

    private function assertReformatted(array $actualLines, array $expectedLines)
    {
        $this->assertEquals(
            $expectedLines,
            explode("\n", $this->removeTrailingWhitespace(implode("\n", $actualLines)))
        );
    }

    private function parse(string $string): NodeInterface
    {
        Symbol::resetGen();
        $parser = (new CompilerFactory())->createParser();
        $tokenStream = (new Lexer())->lexString($string);

        return $parser->parseAll($tokenStream);
    }

    private function removeTrailingWhitespace(string $string)
    {
        $node = $this->parse($string);
        $rule = new RemoveTrailingWhitespaceRule();
        $transformedNode = $rule->transform($node);

        return $transformedNode->getCode();
    }
}
