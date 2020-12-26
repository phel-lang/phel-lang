<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\Tuple;
use Phel\Printer\ElementPrinter\TuplePrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class TuplePrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Tuple $actual): void
    {
        self::assertSame(
            $expected,
            (new TuplePrinter(Printer::readable()))->print($actual)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [$expected = '()', $actual = Tuple::create()];
        yield [$expected = '("a" 1)', $actual = Tuple::create('a', 1)];
        yield [$expected = '[]', $actual = Tuple::createBracket()];
        yield [$expected = '["a" 1]', $actual = Tuple::createBracket('a', 1)];
    }
}
