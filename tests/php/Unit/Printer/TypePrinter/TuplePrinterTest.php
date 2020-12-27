<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\Tuple;
use Phel\Printer\TypePrinter\TuplePrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class TuplePrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Tuple $tuple): void
    {
        self::assertSame(
            $expected,
            (new TuplePrinter(Printer::readable()))->print($tuple)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => '()',
            'tuple' => Tuple::create(),
        ];
        yield [
            'expected' => '("a" 1)',
            'tuple' => Tuple::create('a', 1),
        ];
        yield [
            'expected' => '[]',
            'tuple' => Tuple::createBracket(),
        ];
        yield [
            'expected' => '["a" 1]',
            'tuple' => Tuple::createBracket('a', 1),
        ];
    }
}
