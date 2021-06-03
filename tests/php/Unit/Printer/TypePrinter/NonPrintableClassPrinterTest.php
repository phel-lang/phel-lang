<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use DateTime;
use Generator;
use Phel\Printer\TypePrinter\NonPrintableClassPrinter;
use PHPUnit\Framework\TestCase;
use stdClass;

final class NonPrintableClassPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPrint
     *
     * @param mixed $form
     */
    public function test_print($form, string $expected): void
    {
        self::assertSame($expected, (new NonPrintableClassPrinter())->print($form));
    }

    public function providerPrint(): Generator
    {
        yield 'Empty array' => [
            'form' => new DateTime(),
            'expected ' => 'Printer cannot print this type: DateTime',
        ];

        yield 'simple numeric list' => [
            'form' => new stdClass(),
            'expected ' => 'Printer cannot print this type: stdClass',
        ];
    }
}
