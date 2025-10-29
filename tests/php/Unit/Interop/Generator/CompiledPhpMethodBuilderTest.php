<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop\Generator;

use Phel\Interop\Domain\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Lang\FnInterface;
use PHPUnit\Framework\TestCase;

final class CompiledPhpMethodBuilderTest extends TestCase
{
    private CompiledPhpMethodBuilder $methodBuilder;

    protected function setUp(): void
    {
        $this->methodBuilder = new CompiledPhpMethodBuilder();
    }

    public function test_sanitizes_method_name_with_special_characters(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\func$name@special!';

            public function __invoke(): mixed
            {
                return null;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function funcnamespecial', $result);
    }

    public function test_sanitizes_method_name_starting_with_digit(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\123function';

            public function __invoke(): mixed
            {
                return null;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function _123function', $result);
    }

    public function test_sanitizes_method_name_with_dots(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\test.nested.name';

            public function __invoke(): mixed
            {
                return null;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function testnestedname', $result);
    }

    public function test_preserves_valid_identifiers(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\valid_function_name';

            public function __invoke(): mixed
            {
                return null;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function validFunctionName', $result);
    }

    public function test_includes_return_type_when_defined(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\typed_function';

            public function __invoke(): string
            {
                return '';
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString(': string', $result);
    }

    public function test_defaults_to_mixed_return_type_when_not_defined(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\untyped_function';

            public function __invoke()
            {
                return null;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString(': mixed', $result);
    }

    public function test_handles_variadic_arguments(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\variadic_function';

            public function __invoke(string $first, int ...$rest): int
            {
                return 0;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function variadicFunction($first, ...$rest): int', $result);
    }
}
