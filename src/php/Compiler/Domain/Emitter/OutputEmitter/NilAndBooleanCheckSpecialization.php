<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Shared\CompilerConstants;

use function count;

/**
 * Call-site eligibility checks for the single-argument `phel.core`
 * nil / boolean predicates (`nil?`, `some?`, `true?`, `false?`,
 * `truthy?`) that {@see NodeEmitter\CallEmitter}
 * lowers to a native PHP comparison instead of a registry dispatch.
 */
final readonly class NilAndBooleanCheckSpecialization
{
    private function __construct() {}

    /**
     * `(nil? x)` — single-arg identity check against `nil`. The runtime
     * body is `(id x nil)` which routes through the registry; the
     * specialised emit is the literal `($x === null)`.
     */
    public static function isNilCheck(CallNode $node): bool
    {
        return self::isUnaryCoreCall($node, 'nil?');
    }

    /**
     * `(some? x)` (1-arg) — `(not (nil? x))`. Maps to `($x !== null)`.
     * Skips the 2-arg overload (`(some? pred coll)`); that variant has
     * a different shape and the specialised emit would not be a single
     * native expression.
     */
    public static function isSomeCheck(CallNode $node): bool
    {
        return self::isUnaryCoreCall($node, 'some?');
    }

    /**
     * `(true? x)` — Phel `true?` is identity-strict (`(id x true)`),
     * so the call collapses to `($x === true)` for any argument.
     */
    public static function isTrueCheck(CallNode $node): bool
    {
        return self::isUnaryCoreCall($node, 'true?');
    }

    /**
     * `(false? x)` — `(id x false)`, collapses to `($x === false)`.
     */
    public static function isFalseCheck(CallNode $node): bool
    {
        return self::isUnaryCoreCall($node, 'false?');
    }

    /**
     * `(truthy? x)` — Phel-truthy probe. The runtime body wraps
     * `Truthy::isTruthy($x)`; the inline check is equivalent.
     */
    public static function isTruthyCheck(CallNode $node): bool
    {
        return self::isUnaryCoreCall($node, 'truthy?');
    }

    private static function isUnaryCoreCall(CallNode $node, string $name): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== $name
        ) {
            return false;
        }

        return count($node->getArguments()) === 1;
    }
}
