<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

/**
 * Static return-type table for `php/*` interop calls whose signatures the
 * compiler trusts at face value. The {@see CallSpecialization} numeric /
 * comparison / equality predicates consult this table so a call site like
 * `(+ ^float angle (php/cos a))` can fire the native arithmetic emit path
 * instead of falling back to the runtime dispatch.
 *
 * Scope is deliberately narrow:
 *
 *  - Only fixed-return-shape functions are listed. Variadic-result
 *    functions (`abs`, `round`, `min`, `max`) are excluded because their
 *    return type depends on the caller-supplied argument type; recording
 *    `int|float` here would let the specialiser fire on the wrong PHP
 *    operator (the `+` op is fine for both, but `===` against a mixed-tag
 *    `=` is not).
 *  - `php/aget` is intentionally NOT covered here; its element type is a
 *    property of the source array, not the operator, and propagating it
 *    requires an `:items-type` meta on the binding (deferred). See the
 *    related issue for details.
 *
 * Adding new entries: pick the strictest tag the function guarantees on
 * every input that does not raise. If the function falls back to `false`
 * on bad input (e.g. `strpos`), omit it — a mixed `int|false` cannot
 * splice into a typed-arith expression safely.
 */
final readonly class KnownPhpFunctionReturnTypes
{
    /**
     * Map from the `PhpVarNode` name (no `php/` prefix) to the analyser
     * tag the emitter treats as the call's return type.
     *
     * @var array<string, string>
     */
    private const array RETURN_TYPES = [
        // float-returning scalar math
        'cos' => 'float',
        'sin' => 'float',
        'tan' => 'float',
        'acos' => 'float',
        'asin' => 'float',
        'atan' => 'float',
        'atan2' => 'float',
        'sqrt' => 'float',
        'exp' => 'float',
        'log' => 'float',
        'log10' => 'float',
        'log2' => 'float',
        'pow' => 'float',
        'fmod' => 'float',
        'deg2rad' => 'float',
        'rad2deg' => 'float',
        'pi' => 'float',
        'fdiv' => 'float',
        'hypot' => 'float',
        'lcg_value' => 'float',
        // PHP `floor`/`ceil`/`round` return float, not int (`gettype(floor(3.7)) === "double"`).
        'floor' => 'float',
        'ceil' => 'float',
        'round' => 'float',

        // int-returning
        'intval' => 'int',
        'intdiv' => 'int',
        'count' => 'int',
        'strlen' => 'int',
        'mb_strlen' => 'int',
        'ord' => 'int',
        'crc32' => 'int',
        'random_int' => 'int',
        'mt_rand' => 'int',
        'rand' => 'int',

        // bool-returning
        'is_int' => 'bool',
        'is_integer' => 'bool',
        'is_long' => 'bool',
        'is_float' => 'bool',
        'is_double' => 'bool',
        'is_string' => 'bool',
        'is_array' => 'bool',
        'is_bool' => 'bool',
        'is_callable' => 'bool',
        'is_null' => 'bool',
        'is_numeric' => 'bool',
        'is_object' => 'bool',
        'is_iterable' => 'bool',
        'is_countable' => 'bool',
        'array_key_exists' => 'bool',
        'in_array' => 'bool',
        'ctype_alpha' => 'bool',
        'ctype_digit' => 'bool',
        'ctype_alnum' => 'bool',
        'ctype_space' => 'bool',

        // string-returning
        'strval' => 'string',
        'str_repeat' => 'string',
        'str_pad' => 'string',
        'substr' => 'string',
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
        'chr' => 'string',
        'implode' => 'string',
        'join' => 'string',
        'str_replace' => 'string',
        'bin2hex' => 'string',
        'hex2bin' => 'string',
        'base64_encode' => 'string',
        'json_encode' => 'string',
    ];

    private function __construct() {}

    public static function returnTypeOf(string $phpVarName): ?string
    {
        return self::RETURN_TYPES[$phpVarName] ?? null;
    }

    public static function knows(string $phpVarName): bool
    {
        return isset(self::RETURN_TYPES[$phpVarName]);
    }
}
