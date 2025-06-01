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
            (new PersistentVectorPrinter(Printer::readable()))->print($vector),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'empty vector' => [
            '[]',
            TypeFactory::getInstance()->emptyPersistentVector(),
        ];

        yield 'vector with values' => [
            '["a" 1]',
            TypeFactory::getInstance()->persistentVectorFromArray(['a', 1]),
        ];
    }
}
