<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentVectorPrinter;
use PHPUnit\Framework\TestCase;

final class VectorPrinterTest extends TestCase
{
    /**
     * @dataProvider printerDataProvider
     */
    public function test_print(string $expected, PersistentVectorInterface $vector): void
    {
        self::assertSame(
            $expected,
            (new PersistentVectorPrinter(Printer::readable()))->print($vector)
        );
    }

    public function printerDataProvider(): Generator
    {
        yield 'empty vector' => [
            'expected' => '[]',
            'vector' => TypeFactory::getInstance()->emptyPersistentVector(),
        ];

        yield 'vector with values' => [
            'expected' => '["a" 1]',
            'vector' => TypeFactory::getInstance()->persistentVectorFromArray(['a', 1]),
        ];
    }
}
