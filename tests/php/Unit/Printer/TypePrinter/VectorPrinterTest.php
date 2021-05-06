<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Collections\Vector\TransientVectorInterface;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\VectorPrinter;
use PHPUnit\Framework\TestCase;

final class VectorPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPrint
     *
     * @param PersistentVectorInterface|TransientVectorInterface $vector
     */
    public function testPrint(string $expected, $vector): void
    {
        self::assertSame(
            $expected,
            (new VectorPrinter(Printer::readable()))->print($vector)
        );
    }

    public function providerPrint(): Generator
    {
        $vector = TypeFactory::getInstance()->emptyPersistentVector();

        yield 'persistent empty vector' => [
            'expected' => '[]',
            'vector' => $vector,
        ];

        yield 'persistent vector with values' => [
            'expected' => '["a" 1]',
            'vector' => $vector->append('a')->append(1),
        ];

        yield 'transient empty vector' => [
            'expected' => '[]',
            'vector' => $vector,
        ];

        yield 'transient vector with values' => [
            'expected' => '["a" 1]',
            'vector' => $vector->append('a')->append(1),
        ];
    }
}
