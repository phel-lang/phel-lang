<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PurePhpFunctionReturnTypes;
use PHPUnit\Framework\TestCase;

/**
 * Guards the table's contract: only functions whose result is exactly one
 * primitive on every non-throwing input may be listed, because a tag read
 * from here is grafted onto a binding and propagates through later
 * inference. Argument-typed or `false`-on-error functions must stay out even
 * when the looser emitter table lists them.
 */
final class PurePhpFunctionReturnTypesTest extends TestCase
{
    public function test_int_returning_functions(): void
    {
        foreach (['strlen', 'count', 'intval', 'intdiv', 'ord', 'crc32', 'random_int', 'mt_rand', 'rand'] as $fn) {
            self::assertSame('int', PurePhpFunctionReturnTypes::returnTypeOf($fn), $fn);
        }
    }

    public function test_float_returning_functions(): void
    {
        foreach (['floor', 'ceil', 'round', 'sqrt', 'exp', 'log', 'cos', 'sin', 'tan', 'atan2', 'fmod', 'fdiv', 'hypot', 'pi', 'lcg_value'] as $fn) {
            self::assertSame('float', PurePhpFunctionReturnTypes::returnTypeOf($fn), $fn);
        }
    }

    public function test_bool_returning_functions(): void
    {
        foreach (['is_int', 'is_string', 'is_iterable', 'is_countable', 'array_key_exists', 'in_array', 'ctype_alpha', 'ctype_digit'] as $fn) {
            self::assertSame('bool', PurePhpFunctionReturnTypes::returnTypeOf($fn), $fn);
        }
    }

    public function test_string_returning_functions(): void
    {
        foreach (['strval', 'sprintf', 'str_repeat', 'str_pad', 'substr', 'chr', 'implode', 'join', 'bin2hex', 'base64_encode'] as $fn) {
            self::assertSame('string', PurePhpFunctionReturnTypes::returnTypeOf($fn), $fn);
        }
    }

    /**
     * These live in the emitter's KnownPhpFunctionReturnTypes (which only
     * needs the operator to be right) but must NOT be tagged by the analyzer:
     * their return type depends on the argument or falls back to `false`.
     */
    public function test_argument_dependent_functions_are_excluded(): void
    {
        foreach (['pow', 'str_replace', 'hex2bin', 'json_encode', 'abs', 'min', 'max'] as $fn) {
            self::assertNull(PurePhpFunctionReturnTypes::returnTypeOf($fn), $fn);
        }
    }

    public function test_unknown_function_returns_null(): void
    {
        self::assertNull(PurePhpFunctionReturnTypes::returnTypeOf('some_unlisted_fn'));
    }
}
