<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\FnInterface;
use Phel\Shared\Printer\TypePrinter\FnPrinter;
use PHPUnit\Framework\TestCase;

final class FnPrinterTest extends TestCase
{
    public function test_print(): void
    {
        $class = new class() implements FnInterface {
            public function __invoke(): string
            {
                return 'invoke method';
            }
        };

        self::assertSame('<function>', new FnPrinter()->print($class));
    }

    public function test_print_does_not_deprecate_without_bound_to(): void
    {
        $class = new class() implements FnInterface {
            public function __invoke(): string
            {
                return 'invoke method';
            }
        };

        $deprecations = [];
        set_error_handler(
            static function (int $errno, string $message) use (&$deprecations): bool {
                $deprecations[] = $message;
                return true;
            },
            E_DEPRECATED,
        );

        try {
            $rendered = new FnPrinter()->print($class);
        } finally {
            restore_error_handler();
        }

        self::assertSame('<function>', $rendered);
        self::assertSame([], $deprecations);
    }
}
