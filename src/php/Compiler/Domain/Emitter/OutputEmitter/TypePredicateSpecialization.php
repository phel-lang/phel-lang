<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;

use function count;
use function in_array;

/**
 * Call-site eligibility for the single-argument `phel.core` type and
 * numeric predicates that
 * {@see NodeEmitter\CallEmitter}
 * lowers to a native `instanceof` / `is_*` expression instead of a
 * registry dispatch.
 */
final readonly class TypePredicateSpecialization
{
    private function __construct() {}

    /**
     * `(int? x)` / `(float? x)` / `(double? x)` / `(string? x)` /
     * `(keyword? x)` / `(symbol? x)` / `(ratio? x)` — single-arg
     * type predicates whose runtime body is a one-liner native
     * check. Returns the PHP expression fragment to splice between
     * the surrounding parens, or `null` when the call is not one of
     * these predicates.
     *
     * The fragment expects `%s` substitution for the (already-emitted)
     * argument expression.
     */
    public static function typePredicateFragment(CallNode $node): ?string
    {
        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        return match (PhelCoreCall::nameOf($node)) {
            'int?' => 'is_int(%s)',
            'float?', 'double?' => 'is_float(%s)',
            'string?' => 'is_string(%s)',
            'keyword?' => '(%s instanceof \\Phel\\Lang\\Keyword)',
            'symbol?' => '(%s instanceof \\Phel\\Lang\\Symbol)',
            'ratio?' => '(%s instanceof \\Phel\\Lang\\Ratio)',
            'struct?' => '(%s instanceof \\Phel\\Lang\\Collections\\Struct\\AbstractPersistentStruct)',
            'set?' => '(%s instanceof \\Phel\\Lang\\Collections\\HashSet\\PersistentHashSetInterface)',
            'lazy-seq?' => '(%s instanceof \\Phel\\Lang\\Collections\\LazySeq\\LazySeqInterface)',
            'queue?' => '(%s instanceof \\Phel\\Lang\\Collections\\Queue\\PersistentQueue)',
            'map?' => '((%1$s instanceof \\Phel\\Lang\\Collections\\Map\\PersistentMapInterface) && !(%1$s instanceof \\Phel\\Lang\\Collections\\Struct\\AbstractPersistentStruct))',
            'vector?' => '((%1$s instanceof \\Phel\\Lang\\Collections\\Vector\\PersistentVectorInterface) || (%1$s instanceof \\Phel\\Lang\\Collections\\Map\\MapEntry))',
            'seq?' => '((%1$s instanceof \\Phel\\Lang\\Collections\\LazySeq\\LazySeqInterface) || (%1$s instanceof \\Phel\\Lang\\Collections\\LazySeq\\Cons) || (%1$s instanceof \\Phel\\Lang\\Collections\\LinkedList\\PersistentListInterface))',
            default => null,
        };
    }

    public static function isTypePredicate(CallNode $node): bool
    {
        return self::typePredicateFragment($node) !== null;
    }

    /**
     * `(zero? x)` / `(pos? x)` / `(neg? x)` on an `int` / `float`
     * tagged local. Other numeric shapes (`BigInt`, `Ratio`,
     * `BigDecimal`) need the runtime `NumericOperations` dispatch
     * because the native operators do not honour their equality
     * semantics. Returns the predicate name when eligible, `null`
     * otherwise.
     */
    public static function isNumericPredicate(CallNode $node): ?string
    {
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        if (!in_array($name, ['zero?', 'pos?', 'neg?'], true)) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        $tag = TagNormalizer::normalise($target->getInferredType());
        if ($tag !== 'int' && $tag !== 'float') {
            return null;
        }

        return $name;
    }
}
