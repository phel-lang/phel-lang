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
        yield 'ArrayObject' => [
            new ArrayObject(),
            '#<ArrayObject>',
        ];

        yield 'stdClass' => [
            new stdClass(),
            '#<stdClass>',
        ];

        yield 'namespaced class' => [
            new NonPrintableClassPrinter(),
            '#<Phel\Shared\Printer\TypePrinter\NonPrintableClassPrinter>',
        ];
    }

    public function test_print_is_one_token_naming_the_class(): void
    {
        $printed = new NonPrintableClassPrinter()->print(new ArrayObject());

        self::assertStringNotContainsString(' ', $printed);
        self::assertStringContainsString(ArrayObject::class, $printed);
    }

    public function test_print_with_color_stays_one_token(): void
    {
        $printed = new NonPrintableClassPrinter(withColor: true)->print(new stdClass());

        self::assertStringNotContainsString(' ', $printed);
        self::assertStringContainsString('#<stdClass>', $printed);
    }
}
