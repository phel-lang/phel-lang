<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Rules;

use Phel\Compiler\CompilerFactoryInterface;
use Phel\Formatter\FormatterFactory;
use PHPUnit\Framework\TestCase;

final class UnindentRuleTest extends TestCase
{
    use RuleParserTrait;

    private FormatterFactory $formatterFactory;

    public function setUp(): void
    {
        $this->formatterFactory = new FormatterFactory(
            $this->createMock(CompilerFactoryInterface::class)
        );
    }

    public function testListUnindention(): void
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

    public function testListUnindentionWithComment(): void
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

    private function assertUnindent(array $actualLines, array $expectedLines): void
    {
        self::assertEquals(
            $expectedLines,
            explode("\n", $this->unindent(implode("\n", $actualLines)))
        );
    }

    private function unindent(string $string): string
    {
        return $this->formatterFactory
            ->createUnindentRule()
            ->transform($this->parseStringToNode($string))
            ->getCode();
    }
}
