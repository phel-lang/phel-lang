<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\HashSet\PersistentHashSet;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentHashSetPrinter;
use PHPUnit\Framework\TestCase;

final class HashSetPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function testPrint(string $expected, PersistentHashSet $set): void
    {
        self::assertSame(
            $expected,
            (new PersistentHashSetPrinter(Printer::readable()))->print($set)
        );
    }

    public function printerDataProvider(): Generator
    {
        $set = TypeFactory::getInstance()->emptyPersistentHashSet();

        yield 'empty set' => [
            'expected' => '(set)',
            'set' => $set,
        ];

        yield 'set with one value' => [
            'expected' => '(set "name")',
            'set' => $set->add('name'),
        ];

        yield 'set with multiple values' => [
            'expected' => '(set "key1" "key2")',
            'set' => $set->add('key1')->add('key2'),
        ];
    }
}
