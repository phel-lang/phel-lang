<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

use function count;
use function in_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * Eligibility for the guarded fast path over untyped operands: a core
 * comparison, equality or numeric step whose operands the analyser could
 * not type, lowered to a native PHP expression behind an `is_*` guard, with
 * the runtime call as the fallback (#3351).
 *
 * The typed specialisations only fire when every operand is proven
 * primitive. This covers the call sites they miss: a loop bound, a param
 * compared with a literal, a counter whose type inference gave up on. The
 * guard answers only where the native operator provably agrees with the
 * runtime fn; anything else reaches the runtime fn unchanged:
 *
 * - `<` `<=` `>` `>=`, `pos?`, `neg?`: two scalars. The runtime `lt2` family
 *   rejects `nil` and sends `BigInt` / `Ratio` / `BigDecimal` to
 *   `NumericOperations`, and compares everything else with the native
 *   operator. Neither `nil` nor an object is a scalar.
 * - `=` / `not=`: identical values, or two values the guard pins to one
 *   native type (two ints, a string against a string literal).
 * - `zero?`, `inc`, `dec`: a native int. `(zero? 0.0)` is true and `0.0 ===
 *   0` is not; `inc` at the int bound promotes to `BigInt`.
 *
 * Operands are read more than once, so the emitter spills any operand that
 * is not a local or a literal into a temporary on its first read. Literals
 * are limited to the types each guard reasons about: numbers for ordering,
 * ints and strings for equality. Quoted forms and collection literals
 * decline: they can never pass a scalar guard. Unlike a family in {@see CallSpecialization::isSpecialized()},
 * the lowered shape still calls the runtime fn on the fallback path, so the
 * call keeps its `$__phel_call_N` slot.
 *
 * @internal
 */
final readonly class GuardedCoreCallSpecialization
{
    private const array ORDERING_OPS = [
        '<' => '<',
        '<=' => '<=',
        '>' => '>',
        '>=' => '>=',
    ];

    /** The native comparison each numeric predicate reduces to. */
    private const array NUMERIC_PREDICATES = [
        'zero?' => ' === 0',
        'pos?' => ' > 0',
        'neg?' => ' < 0',
    ];

    /** The guard under which the native operator agrees with the runtime fn. */
    private const array GUARDS = [
        '<' => '\\is_scalar',
        '<=' => '\\is_scalar',
        '>' => '\\is_scalar',
        '>=' => '\\is_scalar',
        'pos?' => '\\is_scalar',
        'neg?' => '\\is_scalar',
        'zero?' => '\\is_int',
        'inc' => '\\is_int',
        'dec' => '\\is_int',
        '=' => '\\is_int',
        'not=' => '\\is_int',
    ];

    private function __construct() {}

    /**
     * The `phel.core` name of a call the guarded path lowers, or `null`.
     */
    public static function nameOf(CallNode $node): ?string
    {
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        $args = $node->getArguments();

        if (isset(self::ORDERING_OPS[$name])) {
            return self::isOrderingEligible($node, $args) ? $name : null;
        }

        if ($name === '=' || $name === 'not=') {
            return self::isEqualityEligible($node, $args) ? $name : null;
        }

        if (isset(self::NUMERIC_PREDICATES[$name])) {
            return self::isUnaryEligible($args) && TypePredicateSpecialization::isNumericPredicate($node) === null
                ? $name
                : null;
        }

        if ($name === 'inc' || $name === 'dec') {
            return self::isUnaryEligible($args) && !NumericOperationSpecialization::isTypedIncDec($node)
                ? $name
                : null;
        }

        return null;
    }

    /**
     * Whether the lowered expression is a PHP `bool` on every path, so an
     * `if` can take it as its test without the truthy adapter. The fallback
     * is the runtime fn, whose fixed arity is tagged `^bool` (and PHP checks
     * it on return) for the comparisons; `pos?` and `neg?` answer with `>`
     * and `<`. `inc` and `dec` produce numbers.
     */
    public static function isBoolReturning(CallNode $node): bool
    {
        $name = self::nameOf($node);

        return !in_array($name, [null, 'inc', 'dec'], true);
    }

    public static function orderingOperator(string $name): ?string
    {
        return self::ORDERING_OPS[$name] ?? null;
    }

    /**
     * The `is_*` function guarding a non-literal operand of `$name`. For
     * `=` / `not=` it applies when both operands are non-literal; against a
     * literal, {@see self::literalGuard()} decides.
     */
    public static function guardFor(string $name): string
    {
        return self::GUARDS[$name] ?? '\\is_int';
    }

    public static function numericPredicateSuffix(string $name): ?string
    {
        return self::NUMERIC_PREDICATES[$name] ?? null;
    }

    /**
     * The `is_*` guard proving a literal operand's type pins the answer,
     * or `null` when the operand is not a literal.
     */
    public static function literalGuard(AbstractNode $operand): ?string
    {
        if (!$operand instanceof LiteralNode) {
            return null;
        }

        return is_string($operand->getValue()) ? '\\is_string' : '\\is_int';
    }

    /**
     * @param list<AbstractNode> $args
     */
    private static function isOrderingEligible(CallNode $node, array $args): bool
    {
        if (count($args) !== 2 || NumericOperationSpecialization::isTypedBinaryOp($node)) {
            return false;
        }

        foreach ($args as $arg) {
            if (self::isNonScalarConstant($arg)
                || ($arg instanceof LiteralNode && !self::isIntLiteral($arg) && !self::isFloatLiteral($arg))
            ) {
                return false;
            }
        }

        return self::hasNonLiteralOperand($args);
    }

    /**
     * Any operands but a pair of literals, with a literal operand an int or a
     * string. `nil`, bool and keyword literals are left to
     * the paths that already lower them to a plain `===`.
     *
     * @param list<AbstractNode> $args
     */
    private static function isEqualityEligible(CallNode $node, array $args): bool
    {
        if (count($args) !== 2 || NumericOperationSpecialization::isTypedBinaryOp($node)) {
            return false;
        }

        foreach ($args as $arg) {
            if (self::isNonScalarConstant($arg)
                || ($arg instanceof LiteralNode && !self::isIntLiteral($arg) && !self::isStringLiteral($arg))
            ) {
                return false;
            }
        }

        return self::hasNonLiteralOperand($args);
    }

    /**
     * @param list<AbstractNode> $args
     */
    private static function isUnaryEligible(array $args): bool
    {
        return count($args) === 1
            && !$args[0] instanceof LiteralNode
            && !self::isNonScalarConstant($args[0]);
    }

    /**
     * A quoted form or a collection literal can never pass a scalar guard,
     * so lowering it would only add the guard to the runtime call.
     */
    private static function isNonScalarConstant(AbstractNode $node): bool
    {
        return $node instanceof QuoteNode
            || $node instanceof VectorNode
            || $node instanceof MapNode
            || $node instanceof SetNode;
    }

    /**
     * @param list<AbstractNode> $args
     */
    private static function hasNonLiteralOperand(array $args): bool
    {
        return !$args[0] instanceof LiteralNode || !$args[1] instanceof LiteralNode;
    }

    private static function isIntLiteral(AbstractNode $node): bool
    {
        return $node instanceof LiteralNode && is_int($node->getValue());
    }

    private static function isFloatLiteral(AbstractNode $node): bool
    {
        return $node instanceof LiteralNode && is_float($node->getValue());
    }

    private static function isStringLiteral(AbstractNode $node): bool
    {
        return $node instanceof LiteralNode && is_string($node->getValue());
    }
}
