<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use ArrayObject;
use Generator;
use Phel\Shared\Printer\TypePrinter\NonPrintableClassPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class NonPrintableClassPrinterTest extends TestCase
{
    #[DataProvider('providerPrint')]
    public function test_print(mixed $form, string $expected): void
    {
        self::assertSame($expected, new NonPrintableClassPrinter()->print($form));
    }

    public static function providerPrint(): Generator
    {
        yield 'cannot print ArrayObject' => [
            new ArrayObject(),
            'Printer cannot print this type: ArrayObject',
        ];

        yield 'cannot print stdClass' => [
            new stdClass(),
            'Printer cannot print this type: stdClass',
        ];
    }
}
