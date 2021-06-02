<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\ObjectPrinter;
use PHPUnit\Framework\TestCase;

final class ObjectPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function test_print(string $expected, object $object): void
    {
        self::assertSame(
            $expected,
            (new ObjectPrinter())->print($object)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'stdClass' => [
            'expected' => '<PHP-Object(stdClass)>',
            'object' => new \stdClass(),
        ];

        yield 'array to object cast' => [
            'expected' => '<PHP-Object(stdClass)>',
            'object' => (object)[],
        ];
    }
}
