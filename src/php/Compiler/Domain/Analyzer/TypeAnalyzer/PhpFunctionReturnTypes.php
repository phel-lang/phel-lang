<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

/**
 * Single source of truth for the return types of `php/*` built-in calls
 * the compiler trusts statically.
 *
 * Two bands:
 *
 *  - {@see self::strictReturnTypeOf()} — functions whose result is exactly
 *    one primitive (`int` / `float` / `bool` / `string`) on every
 *    non-throwing input, regardless of argument types. These tags may
 *    PERSIST: `ReturnTypeInferrer` stamps them on fn returns and
 *    `BindingTypeInferrer` on let/loop bindings, where they feed later
 *    inference (equality, collection access, string concat).
 *  - {@see self::operandReturnTypeOf()} — strict plus a small documented
 *    set of operator-compatible widenings the emitter may use to type an
 *    operand at a single call site. Nothing from the widened band ever
 *    persists on a binding.
 *
 * Membership rules, in order:
 *
 *  - Result depends on argument types (`abs`, `min`, `max`, `round(x, n)`
 *    value-wise)? Excluded from strict. `pow` (`int|float`) is the one
 *    operand-band exception, see below.
 *  - Falls back to `false` or another type family on failure
 *    (`strpos`, `json_encode`, `hex2bin`, `str_replace`)? Excluded from
 *    BOTH bands: a `false` spliced into a native `.` concat emits `""`
 *    where runtime `str` renders `"false"`, silently diverging.
 *  - PHP `floor`/`ceil`/`round` return float, not int
 *    (`gettype(floor(3.7)) === "double"`).
 *
 * Why `pow` may type an operand: its `int|float` is operator-compatible
 * with every native op the emitter splices (`+`, `-`, `*`, `<`, `<=`,
 * `>`, `>=` accept both), and `===` stays consistent because Phel `=` is
 * already strict across int/float (`(= 8 8.0)` is false on the dynamic
 * path too).
 */
final readonly class PhpFunctionReturnTypes
{
    /** @var array<string, string> */
    private const array STRICT_RETURN_TYPES = [
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

        // float — PHP `floor`/`ceil`/`round` return float, not int.
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

    /**
     * Operator-compatible widenings usable ONLY for typing a call-site
     * operand (see the class docblock). Keys must not appear in
     * {@see self::STRICT_RETURN_TYPES}.
     *
     * @var array<string, string>
     */
    private const array OPERAND_WIDENED_RETURN_TYPES = [
        'pow' => 'float',
    ];

    private function __construct() {}

    /**
     * The exact primitive the function returns on every non-throwing
     * input, or `null`. Safe to persist on bindings and fn returns.
     */
    public static function strictReturnTypeOf(string $phpFunctionName): ?string
    {
        return self::STRICT_RETURN_TYPES[$phpFunctionName] ?? null;
    }

    /**
     * Strict band plus the operand-only widenings. For typing a single
     * call-site operand in the emitter; never persist this tag.
     */
    public static function operandReturnTypeOf(string $phpFunctionName): ?string
    {
        return self::STRICT_RETURN_TYPES[$phpFunctionName]
            ?? self::OPERAND_WIDENED_RETURN_TYPES[$phpFunctionName]
            ?? null;
    }
}
