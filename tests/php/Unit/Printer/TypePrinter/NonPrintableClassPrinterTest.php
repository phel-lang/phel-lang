<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use DateTime;
use Generator;
use Phel\Printer\TypePrinter\NonPrintableClassPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class NonPrintableClassPrinterTest extends TestCase
{
    #[DataProvider('providerPrint')]
    public function test_print(mixed $form, string $expected): void
    {
        self::assertSame($expected, (new NonPrintableClassPrinter())->print($form));
    }

    public static function providerPrint(): Generator
    {
        yield 'cannot print DateTime' => [
            new DateTime(),
            'Printer cannot print this type: DateTime',
        ];

        yield 'cannot print stdClass' => [
            new stdClass(),
            'Printer cannot print this type: stdClass',
        ];
    }
}
