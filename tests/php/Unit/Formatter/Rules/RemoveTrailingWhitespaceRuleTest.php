<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactoryInterface;
use Phel\Formatter\FormatterFactory;
use PHPUnit\Framework\TestCase;

final class RemoveTrailingWhitespaceRuleTest extends TestCase
{
    use RuleParserTrait;

    private FormatterFactory $formatterFactory;

    public function setUp(): void
    {
        $this->formatterFactory = new FormatterFactory(
            $this->createMock(CompilerFactoryInterface::class)
        );
    }

    public function testEmptyLine(): void
    {
        $this->assertReformatted(
            ['  '],
            ['']
        );
    }

    public function testAfterList(): void
    {
        $this->assertReformatted(
            ['(foo bar) '],
            ['(foo bar)']
        );
    }

    public function testBetweenList(): void
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

    public function testInsideList(): void
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

    private function assertReformatted(array $actualLines, array $expectedLines): void
    {
        self::assertEquals(
            $expectedLines,
            explode("\n", $this->removeTrailingWhitespace(implode("\n", $actualLines)))
        );
    }

    private function removeTrailingWhitespace(string $string): string
    {
        return $this->formatterFactory
            ->createRemoveTrailingWhitespaceRule()
            ->transform($this->parseStringToNode($string))
            ->getCode();
    }
}
