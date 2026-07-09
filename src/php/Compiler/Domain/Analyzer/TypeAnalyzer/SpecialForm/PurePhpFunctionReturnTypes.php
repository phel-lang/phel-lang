<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

/**
 * Pure PHP built-ins whose return type is a fixed primitive
 * (`int` / `float` / `bool` / `string`) regardless of argument type.
 *
 * The single source of truth for both type-inference passes that read it
 * — {@see ReturnTypeInferrer} (fn return types) and
 * {@see BindingTypeInferrer} (let/loop binding tags). Keeping one table
 * avoids the kind of drift that let `floor`/`ceil` be typed `int` here and
 * `float` there (PHP returns float). Limited to side-effect-free functions
 * with a stable signature across supported PHP versions; anything whose
 * return type depends on its arguments or runtime mode stays out so the
 * inferrers never stamp the wrong tag.
 *
 * Stricter than the emitter's {@see \Phel\Compiler\Domain\Emitter\OutputEmitter\KnownPhpFunctionReturnTypes}:
 * that table may over-widen an operand to make a native operator fire (it
 * tags `pow`/`str_replace`/`json_encode`), because the emitter only needs
 * the operator, not the exact type. A tag written here PERSISTS on the
 * binding and feeds later inference (equality, collection access), so only
 * functions whose result is exactly one primitive on every non-throwing
 * input belong here. Deliberately absent despite being in the emitter table:
 * `pow` (`int|float`), `str_replace` (`string|array`), `hex2bin`/`json_encode`
 * (`string|false`), `abs`/`min`/`max` (argument-typed).
 */
final class PurePhpFunctionReturnTypes
{
    /** @var array<string, string> */
    private const array RETURN_TYPES = [
        // int
        'strlen' => 'int',
        'mb_strlen' => 'int',
        'count' => 'int',
        'intval' => 'int',
        'intdiv' => 'int',
        'ord' => 'int',
        'crc32' => 'int',
        'random_int' => 'int',
        'mt_rand' => 'int',
        'rand' => 'int',

        // float — PHP `floor`/`ceil`/`round` return float, not int (`gettype(floor(3.7)) === "double"`).
        'floatval' => 'float',
        'doubleval' => 'float',
        'floor' => 'float',
        'ceil' => 'float',
        'round' => 'float',
        'sqrt' => 'float',
        'exp' => 'float',
        'log' => 'float',
        'log10' => 'float',
        'log2' => 'float',
        'cos' => 'float',
        'sin' => 'float',
        'tan' => 'float',
        'acos' => 'float',
        'asin' => 'float',
        'atan' => 'float',
        'atan2' => 'float',
        'fmod' => 'float',
        'fdiv' => 'float',
        'hypot' => 'float',
        'deg2rad' => 'float',
        'rad2deg' => 'float',
        'pi' => 'float',
        'lcg_value' => 'float',

        // bool
        'boolval' => 'bool',
        'is_int' => 'bool',
        'is_integer' => 'bool',
        'is_long' => 'bool',
        'is_float' => 'bool',
        'is_double' => 'bool',
        'is_string' => 'bool',
        'is_bool' => 'bool',
        'is_null' => 'bool',
        'is_array' => 'bool',
        'is_object' => 'bool',
        'is_callable' => 'bool',
        'is_numeric' => 'bool',
        'is_iterable' => 'bool',
        'is_countable' => 'bool',
        'array_key_exists' => 'bool',
        'in_array' => 'bool',
        'ctype_alpha' => 'bool',
        'ctype_digit' => 'bool',
        'ctype_alnum' => 'bool',
        'ctype_space' => 'bool',

        // string
        'strval' => 'string',
        'gettype' => 'string',
        'sprintf' => 'string',
        'strtolower' => 'string',
        'strtoupper' => 'string',
        'mb_strtolower' => 'string',
        'mb_strtoupper' => 'string',
        'ucfirst' => 'string',
        'ucwords' => 'string',
        'lcfirst' => 'string',
        'trim' => 'string',
        'ltrim' => 'string',
        'rtrim' => 'string',
        'str_repeat' => 'string',
        'str_pad' => 'string',
        'substr' => 'string',
        'chr' => 'string',
        'implode' => 'string',
        'join' => 'string',
        'bin2hex' => 'string',
        'base64_encode' => 'string',
    ];

    private function __construct() {}

    public static function returnTypeOf(string $phpFunctionName): ?string
    {
        return self::RETURN_TYPES[$phpFunctionName] ?? null;
    }
}
