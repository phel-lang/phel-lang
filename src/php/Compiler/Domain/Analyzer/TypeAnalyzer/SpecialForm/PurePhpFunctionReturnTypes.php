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
 */
final class PurePhpFunctionReturnTypes
{
    /** @var array<string, string> */
    private const array RETURN_TYPES = [
        'strlen' => 'int',
        'intval' => 'int',
        'mb_strlen' => 'int',
        'count' => 'int',
        'random_int' => 'int',
        'intdiv' => 'int',
        'floatval' => 'float',
        'doubleval' => 'float',
        'floor' => 'float',
        'ceil' => 'float',
        'round' => 'float',
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
        'strval' => 'string',
        'strtolower' => 'string',
        'strtoupper' => 'string',
        'mb_strtolower' => 'string',
        'mb_strtoupper' => 'string',
        'trim' => 'string',
        'ltrim' => 'string',
        'rtrim' => 'string',
        'sprintf' => 'string',
        'gettype' => 'string',
    ];

    private function __construct() {}

    public static function returnTypeOf(string $phpFunctionName): ?string
    {
        return self::RETURN_TYPES[$phpFunctionName] ?? null;
    }
}
