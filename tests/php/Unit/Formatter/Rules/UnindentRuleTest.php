<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Rules\UnindentRule;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class UnindentRuleTest extends TestCase
{
    public function testListUnindention()
    {
        $this->assertUnindent(
            [
                '(x a',
                '   b',
                '   c)',
            ],
            [
                '(x a',
                'b',
                'c)',
            ]
        );
    }

    public function testListUnindentionWithComment()
    {
        $this->assertUnindent(
            [
                '(x a',
                '     # my comment',
                '   c)',
            ],
            [
                '(x a',
                '     # my comment',
                'c)',
            ]
        );
    }

    private function assertUnindent(array $actualLines, array $expectedLines)
    {
        $this->assertEquals(
            $expectedLines,
            explode("\n", $this->unindent(implode("\n", $actualLines)))
        );
    }

    private function parse(string $string): NodeInterface
    {
        Symbol::resetGen();
        $parser = (new CompilerFactory())->createParser();
        $tokenStream = (new Lexer())->lexString($string);

        return $parser->parseNext($tokenStream);
    }

    private function unindent(string $string)
    {
        $node = $this->parse($string);
        $rule = new UnindentRule();
        $transformedNode = $rule->transform($node);

        return $transformedNode->getCode();
    }
}
