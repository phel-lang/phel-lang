<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentVectorPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VectorPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
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
            Phel::emptyPersistentVector(),
        ];

        yield 'vector with values' => [
            '["a" 1]',
            Phel::persistentVectorFromArray(['a', 1]),
        ];
    }
}
