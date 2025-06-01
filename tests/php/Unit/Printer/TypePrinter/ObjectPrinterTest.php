<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Printer\TypePrinter\ObjectPrinter;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ObjectPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function test_print(string $expected, object $object): void
    {
        self::assertSame(
            $expected,
            (new ObjectPrinter())->print($object),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'stdClass' => [
            '<PHP-Object(stdClass)>',
            new stdClass(),
        ];

        yield 'array to object cast' => [
            '<PHP-Object(stdClass)>',
            (object)[],
        ];
    }
}
