<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactoryInterface;
use Phel\Formatter\FormatterFactory;
use PHPUnit\Framework\TestCase;

final class RemoveSurroundingWhitespaceRuleTest extends TestCase
{
    use RuleParserTrait;

    private FormatterFactory $formatterFactory;

    public function setUp(): void
    {
        $this->formatterFactory = new FormatterFactory(
            $this->createMock(CompilerFactoryInterface::class)
        );
    }

    public function testList(): void
    {
        $this->assertReformatted(
            ['( 1 2 3 )'],
            ['(1 2 3)']
        );
    }

    public function testBracketList(): void
    {
        $this->assertReformatted(
            ['[ 1 2 3 ]'],
            ['[1 2 3]']
        );
    }

    public function testArray(): void
    {
        $this->assertReformatted(
            ['@[ 1 2 3 ]'],
            ['@[1 2 3]']
        );
    }

    public function testTable(): void
    {
        $this->assertReformatted(
            ['@{ :a 1 :b 2 }'],
            ['@{:a 1 :b 2}']
        );
    }

    public function testRemoveNewlines(): void
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


    private function assertReformatted(array $actualLines, array $expectedLines): void
    {
        self::assertEquals(
            $expectedLines,
            explode("\n", $this->reformat(implode("\n", $actualLines)))
        );
    }

    private function reformat(string $string): string
    {
        return $this->formatterFactory
            ->createRemoveSurroundingWhitespaceRule()
            ->transform($this->parseStringToNode($string))
            ->getCode();
    }
}
