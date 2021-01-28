<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\ArrayPrinter;
use PHPUnit\Framework\TestCase;

final class ArrayPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPrint
     */
    public function testPrint(array $form, string $expected): void
    {
        self::assertSame($expected, (new ArrayPrinter())->print($form));
    }

    public function providerPrint(): Generator
    {
        yield 'Empty array' => [
            'form' => [],
            'expected ' => '<PHP-Array []>',
        ];

        yield 'simple numeric list' => [
            'form' => [1, 2, 3],
            'expected ' => '<PHP-Array [1, 2, 3]>',
        ];

        yield 'simple string list' => [
            'form' => ['s1', 's2', 's3'],
            'expected ' => '<PHP-Array ["s1", "s2", "s3"]>',
        ];

        yield 'key-value dictionary with numbers' => [
            'form' => ['k1' => 1, 'k2' => 2],
            'expected ' => '<PHP-Array ["k1":1, "k2":2]>',
        ];

        yield 'key-value dictionary with strings' => [
            'form' => ['k1' => 'v1', 'k2' => 'v2'],
            'expected ' => '<PHP-Array ["k1":"v1", "k2":"v2"]>',
        ];
    }
}
