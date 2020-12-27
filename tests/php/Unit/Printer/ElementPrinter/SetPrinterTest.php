<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Lang\Set;
use Phel\Printer\ElementPrinter\SetPrinter;
use Phel\Printer\Printer;
use PHPUnit\Framework\TestCase;

final class SetPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, Set $set): void
    {
        self::assertSame(
            $expected,
            (new SetPrinter(Printer::readable()))->print($set)
        );
    }

    public function printerDataProvider(): \Generator
    {
        yield [
            'expected' => '(set)',
            'set' => new Set([])
        ];
        yield [
            'expected' => '(set "name")',
            'set' => new Set(['name'])
        ];
        yield [
            'expected' => '(set "key1" "key2")',
            'set' => new Set(['key1', 'key2'])
        ];
    }
}
