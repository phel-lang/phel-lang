<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SeqInterface;
use Phel\Shared\CompilerConstants;

use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;

/**
 * Syntactic predicates for `CallNode` instances the `CallEmitter`
 * specialises away from the generic dispatch path. The scanner and the
 * emitter consult the same predicates so a specialised call never gets
 * an orphan `static $__phel_call_N` declaration reserved by the cache
 * scanner.
 */
final readonly class CallSpecialization
{
    /**
     * Two-arg `phel.core` arithmetic / ordering ops whose dispatch
     * reduces to a single PHP native op when both args are statically
     * proven `int` / `float`. Maps the Phel name to the PHP operator
     * the emitter splices between the args. `=` is handled separately
     * because it accepts a wider set of primitive tags than the
     * numeric ops.
     *
     * @var array<string, string>
     */
    private const array NUMERIC_BINARY_OPS = [
        '+' => '+',
        '-' => '-',
        '*' => '*',
        '<' => '<',
        '<=' => '<=',
        '>' => '>',
        '>=' => '>=',
    ];

    /** @var list<string> */
    private const array NUMERIC_PRIMITIVE_TAGS = ['int', 'float'];

    /** @var list<string> */
    private const array EQUALITY_PRIMITIVE_TAGS = ['int', 'float', 'bool', 'string'];

    /** @var array<string, string> Phel core seq accessor → PHP method */
    private const array SEQ_METHODS = [
        'first' => 'first',
        'rest' => 'rest',
    ];

    /** @var array<string, true> Tags whose runtime types implement SeqInterface */
    private const array SEQ_TAGS = [
        SeqInterface::class => true,
        PersistentVectorInterface::class => true,
        PersistentListInterface::class => true,
    ];

    private function __construct() {}

    public static function isSpecialized(CallNode $node): bool
    {
        if (self::isStrConcat($node)) {
            return true;
        }

        if (self::isKeywordFind($node)) {
            return true;
        }

        if (self::isTypedGetAccess($node)) {
            return true;
        }

        if (self::isTypedVectorAccessor($node)) {
            return true;
        }

        if (self::isTypedSeqAccessor($node)) {
            return true;
        }

        return self::isTypedBinaryOp($node);
    }

    /**
     * `(nth v i)` / `(count v)` where the analyser has tagged the
     * target as `PersistentVectorInterface`. The runtime `nth` body
     * walks a `cond` over set / seq / vector / map / php-array; for a
     * typed vector every branch collapses to a single method call.
     *
     * @return array{method: string, args: list<int>}|null list of arg
     *                                                     indices to
     *                                                     pass as
     *                                                     method args
     */
    public static function typedVectorMethodCall(CallNode $node): ?array
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $args = $node->getArguments();
        $target = $args[0] ?? null;
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        if (self::normalisedTag($target->getInferredType()) !== PersistentVectorInterface::class) {
            return null;
        }

        $name = $fn->getName()->getName();

        if ($name === 'count' && count($args) === 1) {
            return ['method' => 'count', 'args' => []];
        }

        if ($name === 'nth' && count($args) === 2) {
            return ['method' => 'get', 'args' => [1]];
        }

        return null;
    }

    public static function isTypedVectorAccessor(CallNode $node): bool
    {
        return self::typedVectorMethodCall($node) !== null;
    }

    /**
     * `(first s)` / `(rest s)` where the analyser has tagged the
     * target as a `SeqInterface` / `PersistentVectorInterface` /
     * `PersistentListInterface`. The runtime body of each fn walks a
     * cond chain that handles nil, strings, php-arrays, sets, etc.;
     * for a tagged seq every branch collapses to `$s->first()` /
     * `$s->rest()`.
     */
    public static function typedSeqMethodName(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $name = $fn->getName()->getName();
        if (!isset(self::SEQ_METHODS[$name])) {
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

        $tag = self::normalisedTag($target->getInferredType());
        if ($tag === null || !isset(self::SEQ_TAGS[$tag])) {
            return null;
        }

        return self::SEQ_METHODS[$name];
    }

    public static function isTypedSeqAccessor(CallNode $node): bool
    {
        return self::typedSeqMethodName($node) !== null;
    }

    /**
     * `(<op> a b)` against `phel.core` arithmetic / comparison ops
     * where both args are statically proven primitive. Returns the PHP
     * operator to emit between the args, or `null` when the call is
     * not specialisable.
     */
    public static function typedBinaryOpName(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 2) {
            return null;
        }

        $name = $fn->getName()->getName();

        if (isset(self::NUMERIC_BINARY_OPS[$name])) {
            return self::bothArgsHavePrimitiveTag($args, self::NUMERIC_PRIMITIVE_TAGS)
                ? self::NUMERIC_BINARY_OPS[$name]
                : null;
        }

        if ($name === '=') {
            return self::bothArgsHavePrimitiveTag($args, self::EQUALITY_PRIMITIVE_TAGS)
                ? '==='
                : null;
        }

        return null;
    }

    public static function isTypedBinaryOp(CallNode $node): bool
    {
        return self::typedBinaryOpName($node) !== null;
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
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'get'
        ) {
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

        $tag = self::normalisedTag($target->getInferredType());
        return match ($tag) {
            PersistentVectorInterface::class => 'get',
            PersistentMapInterface::class => 'find',
            default => null,
        };
    }

    /**
     * `(str ...)` whose every argument compiles to a string-typed
     * expression: string literals or `LocalVarNode`s tagged `string`.
     */
    public static function isStrConcat(CallNode $node): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'str'
        ) {
            return false;
        }

        $args = $node->getArguments();
        if ($args === []) {
            return false;
        }

        return array_all($args, static fn(AbstractNode $arg): bool => self::isStringConcatable($arg));
    }

    /**
     * `(:k m)` where the analyser has tagged `m` as `PersistentMapInterface`,
     * so `Keyword::__invoke` reduces to a single `$m->find($k)` call.
     */
    public static function isKeywordFind(CallNode $node): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof LiteralNode || !$fn->getValue() instanceof Keyword) {
            return false;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return false;
        }

        $arg = $args[0];
        return $arg instanceof LocalVarNode
            && self::isPersistentMapTag($arg->getInferredType());
    }

    private static function isStringConcatable(AbstractNode $arg): bool
    {
        if ($arg instanceof LiteralNode && is_string($arg->getValue())) {
            return true;
        }

        if (!$arg instanceof LocalVarNode) {
            return false;
        }

        $tag = $arg->getInferredType();
        return $tag !== null && ltrim($tag, '\\') === 'string';
    }

    private static function isPersistentMapTag(?string $tag): bool
    {
        return self::normalisedTag($tag) === PersistentMapInterface::class;
    }

    private static function normalisedTag(?string $tag): ?string
    {
        return $tag === null ? null : ltrim($tag, '\\');
    }

    /**
     * `true` when every argument compiles to a PHP value the emitter
     * can splice into a native binary op: a primitive literal of the
     * accepted shape, or a `LocalVarNode` whose analyser tag is one of
     * `$acceptedTags` (`int` / `float` for numeric ops, plus `bool` /
     * `string` for equality).
     *
     * @param list<AbstractNode> $args
     * @param list<string>       $acceptedTags
     */
    private static function bothArgsHavePrimitiveTag(array $args, array $acceptedTags): bool
    {
        return array_all(
            $args,
            static fn(AbstractNode $arg): bool => self::isPrimitiveOperand($arg, $acceptedTags),
        );
    }

    /**
     * @param list<string> $acceptedTags
     */
    private static function isPrimitiveOperand(AbstractNode $arg, array $acceptedTags): bool
    {
        if ($arg instanceof LiteralNode) {
            return self::matchesLiteralPrimitive($arg->getValue(), $acceptedTags);
        }

        if (!$arg instanceof LocalVarNode) {
            return false;
        }

        $tag = self::normalisedTag($arg->getInferredType());
        return $tag !== null && in_array($tag, $acceptedTags, true);
    }

    /**
     * @param list<string> $acceptedTags
     */
    private static function matchesLiteralPrimitive(mixed $value, array $acceptedTags): bool
    {
        if (is_int($value)) {
            return in_array('int', $acceptedTags, true);
        }

        if (is_float($value)) {
            return in_array('float', $acceptedTags, true);
        }

        if (is_bool($value)) {
            return in_array('bool', $acceptedTags, true);
        }

        if (is_string($value)) {
            return in_array('string', $acceptedTags, true);
        }

        return false;
    }
}
