<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop\Generator;

use Phel\Interop\Domain\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
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

    public function test_renders_typed_params_in_signature_and_docblock(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\typed_params';

            public function __invoke(int $a, ?string $b): int
            {
                return $a;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function typedParams(int $a, ?string $b): int', $result);
        self::assertStringContainsString(" * @param int \$a\n", $result);
        self::assertStringContainsString(" * @param ?string \$b\n", $result);
        self::assertStringContainsString(" * @return int\n", $result);
        self::assertStringContainsString("callPhel('test-ns', 'typed-params', \$a, \$b)", $result);
    }

    public function test_renders_typed_variadic_param(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\typed_variadic';

            public function __invoke(string $glue, int ...$nums): string
            {
                return $glue . implode($glue, $nums);
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function typedVariadic(string $glue, int ...$nums): string', $result);
        self::assertStringContainsString(' * @param int ...$nums', $result);
        self::assertStringContainsString("callPhel('test-ns', 'typed-variadic', \$glue, ...\$nums)", $result);
    }

    public function test_skips_docblock_when_no_type_information_exists(): void
    {
        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'test_ns\\untyped';

            public function __invoke($a, ...$rest)
            {
                return $a;
            }
        });

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringNotContainsString('/**', $result);
        self::assertStringContainsString('public static function untyped($a, ...$rest): mixed', $result);
    }

    public function test_return_tag_from_meta_fills_docblock_for_untyped_invoke(): void
    {
        // multi-arity fns compile to an untyped `__invoke(...$args)`; the return
        // :tag survives only in the definition metadata
        $functionToExport = new FunctionToExport(
            new class() implements FnInterface {
                public const BOUND_TO = 'test_ns\\multi_arity';

                public function __invoke(...$args)
                {
                    return $args[0] ?? null;
                }
            },
            null,
            'int',
        );

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString('public static function multiArity(...$args): mixed', $result);
        self::assertStringContainsString(' * @param mixed ...$args', $result);
        self::assertStringContainsString(' * @return int', $result);
    }

    public function test_native_return_type_wins_over_return_tag_in_docblock(): void
    {
        $functionToExport = new FunctionToExport(
            new class() implements FnInterface {
                public const BOUND_TO = 'test_ns\\native_return';

                public function __invoke(): string
                {
                    return '';
                }
            },
            null,
            'string',
        );

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString(' * @return string', $result);
        self::assertStringContainsString('(): string', $result);
    }

    public function test_renders_php_attributes_above_method(): void
    {
        $typeFactory = TypeFactory::getInstance();
        $functionToExport = new FunctionToExport(
            new class() implements FnInterface {
                public const BOUND_TO = 'test_ns\\show_product';

                public function __invoke(): mixed
                {
                    return null;
                }
            },
            $typeFactory->persistentVectorFromArray([
                $typeFactory->persistentVectorFromArray([
                    Keyword::create('Route', 'Symfony.Component.Routing.Attribute'),
                    '/products/{id}',
                    $typeFactory->persistentMapFromKVs(
                        Keyword::create('methods'),
                        $typeFactory->persistentVectorFromArray(['GET']),
                    ),
                ]),
            ]),
        );

        $result = $this->methodBuilder->build('test_ns', $functionToExport);

        self::assertStringContainsString(
            "    #[\\Symfony\\Component\\Routing\\Attribute\\Route('/products/{id}', methods: ['GET'])]\n"
            . '    public static function showProduct(',
            $result,
        );
    }
}
