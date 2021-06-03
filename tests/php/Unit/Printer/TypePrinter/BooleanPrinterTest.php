<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\BooleanPrinter;
use PHPUnit\Framework\TestCase;

final class BooleanPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function test_print(string $expected, bool $boolean): void
    {
        self::assertSame(
            $expected,
            (new BooleanPrinter())->print($boolean)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield [
            'expected' => 'true',
            'boolean' => true,
        ];

        yield [
            'expected' => 'false',
            'boolean' => false,
        ];
    }
}
