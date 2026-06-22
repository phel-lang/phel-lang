<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\AbstractFn;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
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
        ) {
            return true;
        }

        return TypePredicateSpecialization::isNumericPredicate($node) !== null;
    }

    public static function isSpecialized(CallNode $node): bool
    {
        if (self::isStrConcat($node)) {
            return true;
        }

        if (TypedValueSpecialization::isKeywordFind($node)) {
            return true;
        }

        if (self::isTypedGetAccess($node)) {
            return true;
        }

        if (GetInSpecialization::isLiteralPathGetIn($node)) {
            return true;
        }

        if (self::isTypedPhpArrayGet($node)) {
            return true;
        }

        if (self::isTypedPhpArrayCount($node)) {
            return true;
        }

        if (self::isTypedStringCount($node)) {
            return true;
        }

        if (self::isTypedStringFirst($node)) {
            return true;
        }

        if (NilAndBooleanCheckSpecialization::isNilCheck($node)) {
            return true;
        }

        if (NilAndBooleanCheckSpecialization::isSomeCheck($node)) {
            return true;
        }

        if (NilAndBooleanCheckSpecialization::isTrueCheck($node)) {
            return true;
        }

        if (NilAndBooleanCheckSpecialization::isFalseCheck($node)) {
            return true;
        }

        if (NilAndBooleanCheckSpecialization::isTruthyCheck($node)) {
            return true;
        }

        if (TypePredicateSpecialization::isNumericPredicate($node) !== null) {
            return true;
        }

        if (TypePredicateSpecialization::isTypePredicate($node)) {
            return true;
        }

        if (TypedValueSpecialization::isNamedAccessor($node)) {
            return true;
        }

        if (TypedValueSpecialization::isEmptyCheck($node)) {
            return true;
        }

        if (TypedValueSpecialization::isContainsCheck($node)) {
            return true;
        }

        if (AtomMethodSpecialization::isAtomMethodCall($node)) {
            return true;
        }

        if (TypedCollectionMethodSpecialization::isTypedVectorAccessor($node)) {
            return true;
        }

        if (TypedCollectionMethodSpecialization::isTypedSeqAccessor($node)) {
            return true;
        }

        if (AssocConjSpecialization::isTypedDissocKeys($node)) {
            return true;
        }

        if (AssocConjSpecialization::isTypedAssocConjDissoc($node)) {
            return true;
        }

        if (AssocConjSpecialization::isAssocConjChain($node)) {
            return true;
        }

        if (NumericOperationSpecialization::isNotEqPeephole($node)) {
            return true;
        }

        if (NumericOperationSpecialization::isTypedVariadicChain($node)) {
            return true;
        }

        return NumericOperationSpecialization::isTypedBinaryOp($node);
    }

    /**
     * `(get coll k)` with two args, where the analyser has tagged the
     * target as either `PersistentVectorInterface` or
     * `PersistentMapInterface`. The runtime `phel.core/get` body walks
     * a `cond` chain covering nil, set, seq, and the generic
     * `php/aget` fallback; for a typed indexed access every branch
     * collapses to a single method call on the target collection.
     */
    public static function isTypedGetAccess(CallNode $node): bool
    {
        return self::typedGetAccessMethod($node) !== null;
    }

    /**
     * Returns the PHP method name to call on the target collection for
     * a `(get coll k)` call the emitter can specialise, or `null` when
     * the call is not a typed get-access.
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

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        $tag = TagNormalizer::normalise($target->getInferredType());
        return match ($tag) {
            PersistentVectorInterface::class => 'get',
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

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return false;
        }

        return TagNormalizer::normalise($target->getInferredType()) === 'array';
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

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return false;
        }

        return TagNormalizer::normalise($target->getInferredType()) === 'array';
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

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return false;
        }

        return TagNormalizer::normalise($target->getInferredType()) === 'string';
    }

    private static function isStringConcatable(AbstractNode $arg): bool
    {
        if ($arg instanceof LiteralNode && is_string($arg->getValue())) {
            return true;
        }

        $tag = TagNormalizer::normalise(NumericOperationSpecialization::inferredTypeOfNode($arg));
        return $tag !== null && $tag === 'string';
    }

}
