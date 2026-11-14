<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

/**
 * Integer arithmetic may promote, so only floating arithmetic has a primitive result tag.
 *
 * @internal
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

        return $hasFloat ? 'float' : null;
    }
}
