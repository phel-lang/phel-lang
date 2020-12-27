<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Printer\ElementPrinter\ObjectPrinter;
use PHPUnit\Framework\TestCase;

final class ObjectPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, object $object): void
    {
        self::assertSame(
            $expected,
            (new ObjectPrinter())->print($object)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => '<PHP-Object(stdClass)>',
            'object' => new \stdClass()
        ];
        yield [
            'expected' => '<PHP-Object(stdClass)>',
            'object' => (object)[]
        ];
    }
}
