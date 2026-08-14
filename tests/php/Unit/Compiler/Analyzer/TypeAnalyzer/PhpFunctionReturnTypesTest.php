<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\PhpFunctionReturnTypes;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use ReflectionFunction;
use ReflectionNamedType;

use function count;
use function function_exists;

/**
 * Guards the table's contract: only functions whose result is exactly one
 * primitive on every non-throwing input may be listed in the strict band,
 * because a tag read from there is grafted onto a binding and propagates
 * through later inference. The operand band may widen only when every
 * native operator the emitter splices stays behavior-identical to the
 * dynamic path.
 */
final class PhpFunctionReturnTypesTest extends TestCase
{
    public function test_int_returning_functions(): void
    {
        foreach (['strlen', 'count', 'intval', 'intdiv', 'ord', 'crc32', 'random_int', 'mt_rand', 'rand'] as $fn) {
            self::assertSame('int', PhpFunctionReturnTypes::strictReturnTypeOf($fn), $fn);
        }
    }

    public function test_float_returning_functions(): void
    {
        foreach (['floor', 'ceil', 'round', 'sqrt', 'exp', 'log', 'cos', 'sin', 'tan', 'atan2', 'fmod', 'fdiv', 'hypot', 'pi', 'lcg_value'] as $fn) {
            self::assertSame('float', PhpFunctionReturnTypes::strictReturnTypeOf($fn), $fn);
        }
    }

    public function test_bool_returning_functions(): void
    {
        foreach (['is_int', 'is_string', 'is_iterable', 'is_countable', 'array_key_exists', 'in_array', 'ctype_alpha', 'ctype_digit'] as $fn) {
            self::assertSame('bool', PhpFunctionReturnTypes::strictReturnTypeOf($fn), $fn);
        }
    }

    public function test_string_returning_functions(): void
    {
        foreach (['strval', 'sprintf', 'str_repeat', 'str_pad', 'substr', 'chr', 'implode', 'join', 'bin2hex', 'base64_encode'] as $fn) {
            self::assertSame('string', PhpFunctionReturnTypes::strictReturnTypeOf($fn), $fn);
        }
    }

    /**
     * Argument-typed or `false`-on-error functions must never be tagged by
     * the analyzer: their tag would persist on bindings and later inference
     * would build on a lie.
     */
    public function test_argument_dependent_functions_are_excluded_from_strict(): void
    {
        foreach (['pow', 'str_replace', 'hex2bin', 'json_encode', 'abs', 'min', 'max'] as $fn) {
            self::assertNull(PhpFunctionReturnTypes::strictReturnTypeOf($fn), $fn);
        }
    }

    /**
     * `false`-on-error functions are excluded from the operand band too: a
     * `false` spliced into a native `.` concat emits `""` where the dynamic
     * `str` path renders `"false"`.
     */
    public function test_conditional_return_functions_are_excluded_from_operand_band(): void
    {
        foreach (['str_replace', 'hex2bin', 'json_encode', 'abs', 'min', 'max'] as $fn) {
            self::assertNull(PhpFunctionReturnTypes::operandReturnTypeOf($fn), $fn);
        }
    }

    public function test_pow_is_operand_band_only(): void
    {
        self::assertNull(PhpFunctionReturnTypes::strictReturnTypeOf('pow'));
        self::assertSame('float', PhpFunctionReturnTypes::operandReturnTypeOf('pow'));
    }

    public function test_operand_band_includes_strict_band(): void
    {
        self::assertSame('int', PhpFunctionReturnTypes::operandReturnTypeOf('count'));
        self::assertSame('string', PhpFunctionReturnTypes::operandReturnTypeOf('bin2hex'));
    }

    public function test_unknown_function_returns_null(): void
    {
        self::assertNull(PhpFunctionReturnTypes::strictReturnTypeOf('some_unlisted_fn'));
        self::assertNull(PhpFunctionReturnTypes::operandReturnTypeOf('some_unlisted_fn'));
    }

    /**
     * Every entry checked against PHP itself rather than against a second
     * hand-written list. The membership rule is "exactly one primitive on
     * every non-throwing input", and for a built-in that is precisely a
     * declared, non-nullable, non-union return type, which reflection can
     * read. This is what catches an entry whose real signature is
     * `string|false`, the failure mode that took `json_encode`, `hex2bin`
     * and `str_replace` back out of the table.
     *
     * It also caught `log2`, which was listed as `float` and is not a PHP
     * function at all: PHP has `log10` and `log($x, 2)`.
     */
    public function test_every_strict_entry_matches_the_real_php_signature(): void
    {
        foreach ($this->strictTable() as $fn => $declared) {
            self::assertTrue(function_exists($fn), "php/{$fn} is not a PHP function");

            $returnType = new ReflectionFunction($fn)->getReturnType();
            self::assertInstanceOf(
                ReflectionNamedType::class,
                $returnType,
                "php/{$fn} has no single declared return type",
            );
            self::assertFalse($returnType->allowsNull(), "php/{$fn} is nullable");
            self::assertSame($declared, $returnType->getName(), "php/{$fn} return type");
        }
    }

    public function test_the_strict_table_is_not_empty(): void
    {
        // Guards the reflection test above against silently passing on an
        // empty table if the constant is ever renamed.
        self::assertGreaterThan(100, count($this->strictTable()));
    }

    /**
     * @return array<string, string>
     */
    private function strictTable(): array
    {
        /** @var array<string, string> $table */
        $table = new ReflectionClassConstant(
            PhpFunctionReturnTypes::class,
            'STRICT_RETURN_TYPES',
        )->getValue();

        return $table;
    }
}
