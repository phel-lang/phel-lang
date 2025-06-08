<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\PrinterInterface;
use Phel\Printer\TypePrinter\ArrayPrinter;
use PHPUnit\Framework\TestCase;

final class ArrayPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPrint
     */
    public function test_print(array $form, string $expected): void
    {
        $printer = new class() implements PrinterInterface {
            public function print($form): string
            {
                return (string)$form;
            }
        };

        self::assertSame($expected, (new ArrayPrinter($printer))->print($form));
    }

    public static function providerPrint(): Generator
    {
        yield 'Empty array' => [
            [],
            '<PHP-Array []>',
        ];

        yield 'simple numeric list' => [
            [1, 2, 3],
            '<PHP-Array [1, 2, 3]>',
        ];

        yield 'simple string list' => [
            ['s1', 's2', 's3'],
            '<PHP-Array [s1, s2, s3]>',
        ];

        yield 'key-value dictionary with numbers' => [
            ['k1' => 1, 'k2' => 2],
            '<PHP-Array [k1:1, k2:2]>',
        ];

        yield 'key-value dictionary with strings' => [
            ['k1' => 'v1', 'k2' => 'v2'],
            '<PHP-Array [k1:v1, k2:v2]>',
        ];
    }
}
