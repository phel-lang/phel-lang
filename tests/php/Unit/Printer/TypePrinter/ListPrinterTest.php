<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentListPrinter;
use PhelType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, PersistentListInterface $list): void
    {
        self::assertSame(
            $expected,
            (new PersistentListPrinter(Printer::readable()))->print($list),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'empty vector' => [
            '()',
            PhelType::emptyPersistentList(),
        ];

        yield 'vector with values' => [
            '("a" 1)',
            PhelType::persistentListFromArray(['a', 1]),
        ];
    }
}
