<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentListPrinter;
use PHPUnit\Framework\TestCase;

final class ListPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function test_print(string $expected, PersistentListInterface $list): void
    {
        self::assertSame(
            $expected,
            (new PersistentListPrinter(Printer::readable()))->print($list)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'empty vector' => [
            'expected' => '()',
            'list' => TypeFactory::getInstance()->emptyPersistentList(),
        ];

        yield 'vector with values' => [
            'expected' => '("a" 1)',
            'list' => TypeFactory::getInstance()->persistentListFromArray(['a', 1]),
        ];
    }
}
