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

    public function test_sanitizes_invalid_characters_and_starts_with_digit(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\123func$name@special!';

            public function __invoke(): mixed
            {
                return null;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        // Invalid characters replaced with underscores, prepended with _ since starts with digit
        self::assertStringContainsString('public static function _123func_name_special', $result);
    }

    public function test_sanitizes_pure_operator_symbols(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\>=';

            public function __invoke(): mixed
            {
                return null;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        // Pure operators fallback to "operator"
        self::assertStringContainsString('public static function operator', $result);
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
}
