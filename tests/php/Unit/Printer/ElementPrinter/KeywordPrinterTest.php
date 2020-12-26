<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\Keyword;
use Phel\Printer\ElementPrinter\KeywordPrinter;
use PHPUnit\Framework\TestCase;

final class KeywordPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Keyword $actual): void
    {
        self::assertSame(
            $expected,
            (new KeywordPrinter())->print($actual)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [$expected = ':name', $actual = new Keyword('name')];
        yield [$expected = ':\\?#__\|\/', $actual = new Keyword('\\?#__\|\/')];
    }
}
