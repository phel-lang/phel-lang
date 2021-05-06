<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Collections\Vector\TransientVectorInterface;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentVectorPrinter;
use PHPUnit\Framework\TestCase;

final class VectorPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPersistentVector
     */
    public function testPrintPersistent(string $expected, PersistentVectorInterface $vector): void
    {
        self::assertSame(
            $expected,
            (new PersistentVectorPrinter(Printer::readable()))->print($vector)
        );
    }

    public function providerPersistentVector(): Generator
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
    /**
     * @dataProvider providerTransientVector
     */
    public function testPrintTransient(string $expected, TransientVectorInterface $vector): void
    {
        self::assertSame(
            $expected,
            (new PersistentVectorPrinter(Printer::readable()))->print($vector)
        );
    }

    public function providerTransientVector(): Generator
    {
        yield 'empty vector' => [
            'expected' => '[]',
            'vector' => TypeFactory::getInstance()->emptyPersistentVector()->asTransient(),
        ];

        yield 'vector with values' => [
            'expected' => '["a" 1]',
            'vector' => TypeFactory::getInstance()->persistentVectorFromArray(['a', 1])->asTransient(),
        ];
    }
}
