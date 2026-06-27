<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use function in_array;

/**
 * PHP's result-type rule for the operand-preserving arithmetic operators
 * (`+`, `-`, `*`): `int` when every operand is `int`, `float` when any operand
 * is `float`, and `null` (not statically numeric) as soon as one operand is
 * neither. The single source of truth for both type-inference passes that need
 * it — {@see ReturnTypeInferrer} (fn return types) and
 * {@see BindingTypeInferrer} (let/loop binding tags) — so the promotion rule
 * cannot drift between them.
 */
final class ArithmeticResultType
{
    private function __construct() {}

    /**
     * @param list<?string> $operandTypes the resolved type tag of each operand
     *                                    (`int` / `float`, or `null`/other when not statically numeric)
     */
    public static function fromOperands(array $operandTypes): ?string
    {
        $hasFloat = false;
        foreach ($operandTypes as $type) {
            if ($type === 'float') {
                $hasFloat = true;
                continue;
            }

            if ($type !== 'int') {
                return null;
            }
        }

        return $hasFloat ? 'float' : 'int';
    }

    public static function isFloatOrInt(?string $type): bool
    {
        return in_array($type, ['int', 'float'], true);
    }
}
