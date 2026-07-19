<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\AbstractFn;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\FnInterface;

use function count;
use function is_string;

/**
 * Syntactic predicates for `CallNode` instances the `CallEmitter`
 * specialises away from the generic dispatch path. The scanner and the
 * emitter consult the same predicates so a specialised call never gets
 * an orphan `static $__phel_call_N` declaration reserved by the cache
 * scanner.
 */
final readonly class CallSpecialization
{
    private function __construct() {}

    /**
     * Subset of {@see self::isSpecialized()} that lowers to a bool-typed
     * PHP expression. The `BooleanExprDetector` consults this so the
     * `IfEmitter` can splice the call directly into the test slot
     * without wrapping it in the Phel-truthy adapter.
     */
    public static function isBoolReturningSpecialisation(CallNode $node): bool
    {
        if (NilAndBooleanCheckSpecialization::isNilCheck($node)
            || NilAndBooleanCheckSpecialization::isSomeCheck($node)
            || NilAndBooleanCheckSpecialization::isTrueCheck($node)
            || NilAndBooleanCheckSpecialization::isFalseCheck($node)
            || NilAndBooleanCheckSpecialization::isTruthyCheck($node)
            || TypePredicateSpecialization::isTypePredicate($node)
            || TypedValueSpecialization::isEmptyCheck($node)
            || TypedValueSpecialization::isContainsCheck($node)
            || NumericOperationSpecialization::isNotEqPeephole($node)
        ) {
            return true;
        }

        if (TypePredicateSpecialization::isNumericPredicate($node) !== null) {
            return true;
        }

        return NumericOperationSpecialization::isTypedComparison($node);
    }

    public static function isSpecialized(CallNode $node): bool
    {
        if (self::isStrConcat($node)
            || TypedValueSpecialization::isKeywordFind($node)
            || self::isTypedGetAccess($node)
            || GetInSpecialization::isLiteralPathGetIn($node)
            || self::isTypedPhpArrayGet($node)
            || self::isTypedPhpArrayCount($node)
            || self::isTypedStringCount($node)
            || self::isTypedStringFirst($node)
            || NilAndBooleanCheckSpecialization::isNilCheck($node)
            || NilAndBooleanCheckSpecialization::isSomeCheck($node)
            || NilAndBooleanCheckSpecialization::isTrueCheck($node)
            || NilAndBooleanCheckSpecialization::isFalseCheck($node)
            || NilAndBooleanCheckSpecialization::isTruthyCheck($node)
            || TypePredicateSpecialization::isNumericPredicate($node) !== null
            || TypePredicateSpecialization::isTypePredicate($node)
            || TypedValueSpecialization::isNamedAccessor($node)
            || TypedValueSpecialization::isEmptyCheck($node)
            || TypedValueSpecialization::isContainsCheck($node)
            || AtomMethodSpecialization::isAtomMethodCall($node)
            || TypedCollectionMethodSpecialization::isTypedVectorAccessor($node)
            || TypedCollectionMethodSpecialization::isTypedSeqAccessor($node)
            || AssocConjSpecialization::isTypedDissocKeys($node)
            || AssocConjSpecialization::isTypedAssocConjDissoc($node)
            || AssocConjSpecialization::isAssocConjChain($node)
            || NumericOperationSpecialization::isNotEqPeephole($node)
            || NumericOperationSpecialization::isTypedVariadicChain($node)
            || NumericOperationSpecialization::isTypedIncDec($node)
            || ReduceSpecialization::isTypedVectorReduce($node)
            || NumericOperationSpecialization::squaredBase($node) instanceof AbstractNode
        ) {
            return true;
        }

        return NumericOperationSpecialization::isTypedBinaryOp($node);
    }

    /**
     * `(get map k)` with two args, where the analyser has tagged the
     * target as `PersistentMapInterface`. The runtime `phel.core/get`
     * body walks a `cond` chain covering nil, set, seq, and the generic
     * `php/aget` fallback; for a typed map lookup every branch collapses
     * to the nil-safe `find` method call.
     */
    public static function isTypedGetAccess(CallNode $node): bool
    {
        return self::typedGetAccessMethod($node) !== null;
    }

    /**
     * Returns the PHP method name to call on the target collection for
     * a `(get coll k)` call the emitter can specialise, or `null` when
     * the call is not a typed get-access.
     *
     * A tagged VECTOR `get` is deliberately NOT specialised here: unlike
     * the map's nil-safe `find`, `PersistentVector::get(int)` throws when
     * the index is out of range and TypeErrors on a non-int key, whereas
     * runtime `phel.core/get` returns nil for both (it guards non-int keys
     * and out-of-range access). Delegating vector `get` to the runtime
     * keeps those semantics; `nth` remains the specialised O(1) accessor.
     * A correct fast vector `get` (int-check + bounds guard with single
     * evaluation of the key) is left as follow-up.
     */
    public static function typedGetAccessMethod(CallNode $node): ?string
    {
        if (!PhelCoreCall::is($node, 'get')) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 2) {
            return null;
        }

        return match (TagNormalizer::ofLocalVar($args[0])) {
            PersistentMapInterface::class => 'find',
            default => null,
        };
    }

    /**
     * Call whose target is a `LocalVarNode` tagged with the abstract Phel
     * fn base class (`\Phel\Lang\AbstractFn`) or the marker interface
     * (`\Phel\Lang\FnInterface`). The generic dispatch path emits
     * `($f)($args)`, which goes through PHP's `__invoke` magic call.
     * When the tag asserts the value is an `AbstractFn` (or anything that
     * implements `FnInterface`), the emitter can call `__invoke` directly
     * and skip the magic-method resolution.
     *
     * The runtime contract is the same `__invoke` signature emitted by
     * `FnAsClassEmitter`, so a tag mismatch becomes a method-not-found
     * error at runtime instead of silent fallback — that is a deliberate
     * trade-off: tags are user-asserted, the emitter trusts them.
     */
    public static function isTypedAFnLocal(CallNode $node): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof LocalVarNode) {
            return false;
        }

        $tag = TagNormalizer::normalise($fn->getInferredType());
        if ($tag === null) {
            return false;
        }

        return $tag === AbstractFn::class
            || $tag === FnInterface::class;
    }

    /**
     * `(get arr k)` or `(get arr k default)` where the analyser has
     * tagged `arr` as a PHP `array`. The runtime `get` body for the
     * `:else` branch is `(let [res (php/aget ds k)] (if (nil? res) opt res))`,
     * which is semantically `$arr[$k] ?? $default` — both absent keys
     * and explicit null values fall through to the default.
     */
    public static function isTypedPhpArrayGet(CallNode $node): bool
    {
        if (!PhelCoreCall::is($node, 'get')) {
            return false;
        }

        $args = $node->getArguments();
        $argc = count($args);
        if ($argc !== 2 && $argc !== 3) {
            return false;
        }

        return TagNormalizer::ofLocalVar($args[0]) === 'array';
    }

    /**
     * `(count arr)` where the analyser has tagged `arr` as a PHP
     * `array`. The runtime `count` body walks a cond chain over the
     * standard collection shapes before reaching `(php/count ds)`; for
     * an `array`-tagged target the only branch that ever fires is the
     * native one.
     */
    public static function isTypedPhpArrayCount(CallNode $node): bool
    {
        if (!PhelCoreCall::is($node, 'count')) {
            return false;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return false;
        }

        return TagNormalizer::ofLocalVar($args[0]) === 'array';
    }

    /**
     * `(count s)` where the analyser has tagged `s` as a PHP `string`.
     * The runtime `count` body walks a cond chain over the standard
     * collection shapes before reaching `(php/mb_strlen coll)` for the
     * `(php/is_string coll)` branch; for a `string`-tagged target the
     * only branch that ever fires is that multibyte length. The emitter
     * lowers it to `mb_strlen($s)` — the same default-encoding call the
     * runtime makes, so it stays byte-for-byte equivalent for multibyte
     * input.
     */
    public static function isTypedStringCount(CallNode $node): bool
    {
        return self::isTypedStringCall($node, 'count');
    }

    /**
     * `(first s)` where the analyser has tagged `s` as a PHP `string`.
     * The runtime `first` body reaches `first-of-string` for the
     * `(php/is_string xs)` branch, which is
     * `(if (php/=== "" s) nil (php/mb_substr s 0 1))`. The emitter lowers
     * it to `($s === '' ? null : mb_substr($s, 0, 1))` — the same
     * default-encoding multibyte slice plus the empty-string nil guard,
     * preserving the contract that an empty string yields nil.
     */
    public static function isTypedStringFirst(CallNode $node): bool
    {
        return self::isTypedStringCall($node, 'first');
    }

    /**
     * `(str ...)` whose every argument compiles to a string-typed
     * expression: string literals or `LocalVarNode`s tagged `string`.
     */
    public static function isStrConcat(CallNode $node): bool
    {
        if (!PhelCoreCall::is($node, 'str')) {
            return false;
        }

        $args = $node->getArguments();
        if ($args === []) {
            return false;
        }

        return array_all($args, static fn(AbstractNode $arg): bool => self::isStringConcatable($arg));
    }

    /**
     * Shared shape check for a single-arg `phel.core` call whose only
     * argument is a `LocalVarNode` tagged as a PHP `string`.
     */
    private static function isTypedStringCall(CallNode $node, string $fnName): bool
    {
        if (!PhelCoreCall::is($node, $fnName)) {
            return false;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return false;
        }

        return TagNormalizer::ofLocalVar($args[0]) === 'string';
    }

    private static function isStringConcatable(AbstractNode $arg): bool
    {
        if ($arg instanceof LiteralNode && is_string($arg->getValue())) {
            return true;
        }

        return TagNormalizer::normalise(NumericOperationSpecialization::inferredTypeOfNode($arg)) === 'string';
    }

}
