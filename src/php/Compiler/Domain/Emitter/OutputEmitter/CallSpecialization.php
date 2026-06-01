<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Lang\AbstractFn;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SeqInterface;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;

use function array_slice;
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
            || self::isTypePredicate($node)
            || self::isEmptyCheck($node)
            || self::isContainsCheck($node)
        ) {
            return true;
        }

        return self::isNumericPredicate($node) !== null;
    }

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

        if (self::isTypedPhpArrayGet($node)) {
            return true;
        }

        if (self::isTypedPhpArrayCount($node)) {
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

        if (self::isNumericPredicate($node) !== null) {
            return true;
        }

        if (self::isTypePredicate($node)) {
            return true;
        }

        if (self::isNamedAccessor($node)) {
            return true;
        }

        if (self::isEmptyCheck($node)) {
            return true;
        }

        if (self::isContainsCheck($node)) {
            return true;
        }

        if (AtomMethodSpecialization::isAtomMethodCall($node)) {
            return true;
        }

        if (self::isTypedVectorAccessor($node)) {
            return true;
        }

        if (self::isTypedSeqAccessor($node)) {
            return true;
        }

        if (self::isTypedAssocConjDissoc($node)) {
            return true;
        }

        if (self::isAssocConjChain($node)) {
            return true;
        }

        if (self::isNotEqPeephole($node)) {
            return true;
        }

        if (self::isTypedVariadicChain($node)) {
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
     * `(assoc coll k v)` 3-arg / `(conj coll x)` 2-arg / `(dissoc coll k)` 2-arg
     * specialise to a direct method call when the target carries an
     * inferred persistent-collection tag:
     *
     *  - `PersistentMapInterface`  → `put` / `cons` (n/a) / `remove`
     *  - `PersistentVectorInterface` → `update` / `append` / (n/a)
     *
     * Skips variadic / extra-arg arities — those need a loop the runtime
     * dispatch handles separately.
     */
    public static function typedAssocConjDissocMethod(CallNode $node): ?string
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

        $tag = self::normalisedTag($target->getInferredType());
        if ($tag === null) {
            return null;
        }

        $name = $fn->getName()->getName();
        $argCount = count($args);

        if ($name === 'assoc' && $argCount === 3) {
            return match ($tag) {
                PersistentMapInterface::class => 'put',
                PersistentVectorInterface::class => 'update',
                default => null,
            };
        }

        if ($name === 'conj' && $argCount === 2) {
            return match ($tag) {
                PersistentVectorInterface::class => 'append',
                default => null,
            };
        }

        if ($name === 'dissoc' && $argCount === 2 && $tag === PersistentMapInterface::class) {
            return 'remove';
        }

        return null;
    }

    public static function isTypedAssocConjDissoc(CallNode $node): bool
    {
        return self::typedAssocConjDissocMethod($node) !== null;
    }

    /**
     * Detects an `(-> m (assoc k v) (assoc k v) ...)` or
     * `(-> v (conj x) (conj y) ...)` chain — after thread-macro
     * expansion these are nested `CallNode`s of the same op terminating
     * at a `LocalVarNode` whose tag matches `PersistentMapInterface` or
     * `PersistentVectorInterface`. A chain of length 1 is **not**
     * batched: the existing single-call specialiser already lowers it
     * to a direct method call, and a transient round-trip would just
     * add work.
     *
     * Returns the leaf target, the transient method to spam, and the
     * argument groups (`[k, v]` pairs for `assoc`, single-element
     * `[x]` lists for `conj`) — `null` when the call is not a chain.
     *
     * @return array{
     *     target: LocalVarNode,
     *     method: 'append'|'put',
     *     groups: list<list<AbstractNode>>
     * }|null
     */
    public static function assocConjChain(CallNode $node): ?array
    {
        $shape = self::classifyChainHead($node);
        if ($shape === null) {
            return null;
        }

        [$opName, $expectedArgCount, $method, $expectedTag] = $shape;

        $groups = [];
        $current = $node;
        while (true) {
            $cFn = $current->getFn();
            if (!$cFn instanceof GlobalVarNode) {
                return null;
            }

            if ($cFn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
                || $cFn->getName()->getName() !== $opName
            ) {
                return null;
            }

            $cArgs = $current->getArguments();
            if (count($cArgs) !== $expectedArgCount) {
                return null;
            }

            array_unshift($groups, array_slice($cArgs, 1));

            $target = $cArgs[0];
            if ($target instanceof CallNode) {
                $current = $target;
                continue;
            }

            if (!$target instanceof LocalVarNode) {
                return null;
            }

            if (self::normalisedTag($target->getInferredType()) !== $expectedTag) {
                return null;
            }

            if (count($groups) < 2) {
                return null;
            }

            return [
                'target' => $target,
                'method' => $method,
                'groups' => $groups,
            ];
        }
    }

    public static function isAssocConjChain(CallNode $node): bool
    {
        return self::assocConjChain($node) !== null;
    }

    /**
     * `(not (= a b))` where the inner `=` is already typed-binary-op
     * specialisable (`===` between two typed primitives). The
     * peephole emits `($a !== $b)` directly, sparing the runtime
     * `phel.core/not` dispatch and the `!` wrapper.
     *
     * Returns the inner `=` `CallNode` so the emitter can splice the
     * args between `!==`.
     */
    public static function notEqPeepholeInner(CallNode $node): ?CallNode
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'not'
        ) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        $inner = $args[0];
        if (!$inner instanceof CallNode) {
            return null;
        }

        if (self::typedBinaryOpName($inner) !== '===') {
            return null;
        }

        return $inner;
    }

    public static function isNotEqPeephole(CallNode $node): bool
    {
        return self::notEqPeepholeInner($node) instanceof CallNode;
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
     * Variadic (N>=3 args) `phel.core` numeric / ordering ops where the
     * analyser has tagged **every** operand as a primitive
     * `LocalVarNode` (`^int` / `^float`). Returns the PHP operator to
     * splice between consecutive operands together with a `kind`
     * discriminating chained arithmetic from chained comparison emission.
     *
     * Literals are deliberately excluded for N>=3: a pure-literal int
     * chain that the constant folder refused (because the product would
     * exceed `PHP_INT_MAX`) must keep its runtime dispatch so
     * `BigInt` / `Ratio` promotion still triggers. The user opts into the
     * primitive-only trade-off by tagging the binding.
     *
     * @return array{op: string, kind: 'arith'|'compare'}|null
     */
    public static function typedVariadicChain(CallNode $node): ?array
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) < 3) {
            return null;
        }

        if (!self::allArgsAreTaggedNumericLocals($args)) {
            return null;
        }

        $name = $fn->getName()->getName();

        if (in_array($name, ['+', '*'], true)) {
            return ['op' => self::NUMERIC_BINARY_OPS[$name], 'kind' => 'arith'];
        }

        if (in_array($name, ['<', '<=', '>', '>='], true)) {
            return ['op' => self::NUMERIC_BINARY_OPS[$name], 'kind' => 'compare'];
        }

        return null;
    }

    public static function isTypedVariadicChain(CallNode $node): bool
    {
        return self::typedVariadicChain($node) !== null;
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

        $tag = self::normalisedTag($fn->getInferredType());
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
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'get'
        ) {
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

        return self::normalisedTag($target->getInferredType()) === 'array';
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
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'count'
        ) {
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

        return self::normalisedTag($target->getInferredType()) === 'array';
    }

    /**
     * `(contains? coll k)` on a target tagged as a
     * `\Phel\Lang\ContainsInterface`-implementing collection
     * (PersistentMap / PersistentVector / PersistentHashSet) or as a
     * PHP `array`. The runtime body walks
     * `nil? → ContainsInterface → is_array → is_string → throw`; the
     * tagged target collapses to one of the first two branches.
     *
     * Returns:
     *  - `'method'` for ContainsInterface targets — emit `$coll->contains($k)`
     *  - `'array'` for array tags                 — emit `array_key_exists($k, $coll)`
     *  - `null` otherwise.
     */
    public static function containsCheckKind(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'contains?'
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
        if ($tag === null) {
            return null;
        }

        if ($tag === 'array') {
            return 'array';
        }

        $containsInterfaces = [
            PersistentMapInterface::class,
            PersistentVectorInterface::class,
            PersistentHashSetInterface::class,
            ContainsInterface::class,
        ];

        return in_array($tag, $containsInterfaces, true) ? 'method' : null;
    }

    public static function isContainsCheck(CallNode $node): bool
    {
        return self::containsCheckKind($node) !== null;
    }

    /**
     * `(empty? x)` on a tagged local. Returns the PHP expression
     * fragment with `%s` substitution for the (already-emitted)
     * argument, or `null` when the call is not eligible.
     *
     *  - `^array x`                       → `(%s === [])`
     *  - `^string x`                      → `(%s === '')`
     *  - `^int x`                         → `(%s === 0)`
     *  - `^PersistentMapInterface x`      → `(%s->count() === 0)`
     *  - `^PersistentVectorInterface x`   → `(%s->count() === 0)`
     */
    public static function emptyCheckFragment(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'empty?'
        ) {
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
        if ($tag === null) {
            return null;
        }

        return match ($tag) {
            'array' => '(%s === [])',
            'string' => "(%s === '')",
            'int' => '(%s === 0)',
            PersistentMapInterface::class,
            PersistentVectorInterface::class => '(%s->count() === 0)',
            default => null,
        };
    }

    public static function isEmptyCheck(CallNode $node): bool
    {
        return self::emptyCheckFragment($node) !== null;
    }

    /**
     * `(name x)` / `(namespace x)` on a target tagged
     * `\Phel\Lang\Keyword` or `\Phel\Lang\Symbol`. The runtime body
     * for `name` is `(if (string? x) x (php/-> x (getName)))`; the
     * tagged target always hits the second branch. Returns the
     * method name (`getName` / `getNamespace`) when eligible, `null`
     * otherwise.
     */
    public static function namedAccessorMethod(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
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
        if ($tag !== Keyword::class && $tag !== Symbol::class) {
            return null;
        }

        return match ($fn->getName()->getName()) {
            'name' => 'getName',
            'namespace' => 'getNamespace',
            default => null,
        };
    }

    public static function isNamedAccessor(CallNode $node): bool
    {
        return self::namedAccessorMethod($node) !== null;
    }

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
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        return match ($fn->getName()->getName()) {
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
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
            return null;
        }

        $name = $fn->getName()->getName();
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

        $tag = self::normalisedTag($target->getInferredType());
        if ($tag !== 'int' && $tag !== 'float') {
            return null;
        }

        return $name;
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

    /**
     * @return array{0: string, 1: int, 2: 'append'|'put', 3: class-string}|null
     */
    private static function classifyChainHead(CallNode $node): ?array
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
            return null;
        }

        return match ($fn->getName()->getName()) {
            'assoc' => ['assoc', 3, 'put', PersistentMapInterface::class],
            'conj' => ['conj', 2, 'append', PersistentVectorInterface::class],
            default => null,
        };
    }

    private static function isStringConcatable(AbstractNode $arg): bool
    {
        if ($arg instanceof LiteralNode && is_string($arg->getValue())) {
            return true;
        }

        $tag = self::normalisedTag(self::inferredTypeOfNode($arg));
        return $tag !== null && $tag === 'string';
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
     * @param list<AbstractNode> $args
     */
    private static function allArgsAreTaggedNumericLocals(array $args): bool
    {
        return array_all(
            $args,
            static function (AbstractNode $arg): bool {
                $tag = self::normalisedTag(self::inferredTypeOfNode($arg));
                return $tag !== null && in_array($tag, self::NUMERIC_PRIMITIVE_TAGS, true);
            },
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

        $tag = self::normalisedTag(self::inferredTypeOfNode($arg));
        return $tag !== null && in_array($tag, $acceptedTags, true);
    }

    /**
     * Static type the emitter can attribute to `$arg`'s emitted PHP
     * expression. Handles two shapes:
     *
     *  - `LocalVarNode` — analyser-resolved binding tag (`^int` / `^float`
     *    / a class FQN). The numeric / equality specialiser was built
     *    around this case in PR #2148.
     *  - `CallNode` whose `fn` is a `PhpVarNode` listed in
     *    {@see KnownPhpFunctionReturnTypes}. The fn name resolves to a
     *    PHP scalar function whose return shape is fixed (`php/cos` →
     *    `float`, `php/count` → `int`, etc.), so the call site can be
     *    spliced into a native binary op the same way a tagged local
     *    can. This is the path closing #2175 — real game / raycaster
     *    code reads `(+ ^float angle (php/cos a))`, not
     *    `(+ ^float a ^float b)`.
     *
     * Returns `null` when the node carries no usable type, which leaves
     * the call site on the generic runtime dispatch.
     */
    private static function inferredTypeOfNode(AbstractNode $arg): ?string
    {
        if ($arg instanceof LocalVarNode) {
            return $arg->getInferredType();
        }

        if (!$arg instanceof CallNode) {
            return null;
        }

        $fn = $arg->getFn();
        if ($fn instanceof PhpVarNode) {
            return KnownPhpFunctionReturnTypes::returnTypeOf($fn->getName());
        }

        // Nested typed-arith / typed-equality calls keep their numeric tag so
        // an outer specialiser fires on the chained shape. `(+ a (* b c))`
        // with all-numeric operands lowers `(* b c)` to a native PHP product;
        // the outer `+` must see that product as `float` (when any operand is
        // float) or `int` so it can emit `($a + ($b * $c))` instead of a
        // runtime dispatch around the inner native expression.
        return self::numericTypeOfTypedBinaryCall($arg);
    }

    /**
     * Result tag of a numeric typed-arith call (`+`, `-`, `*`) when every
     * operand carries a known numeric tag. The promotion rule mirrors
     * PHP's arithmetic operators: any `float` operand yields `float`,
     * otherwise `int`. Comparison ops return `bool` and aren't covered
     * here because the caller wants a numeric tag to splice into another
     * arithmetic op; equality / comparison feed the boolean-expression
     * detector instead.
     *
     * Recursion is bounded by AST depth — each call inspects strictly
     * smaller subexpressions.
     */
    private static function numericTypeOfTypedBinaryCall(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
            return null;
        }

        $name = $fn->getName()->getName();
        if (!in_array($name, ['+', '-', '*'], true)) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) < 2) {
            return null;
        }

        $anyFloat = false;
        foreach ($args as $arg) {
            $tag = self::operandNumericTag($arg);
            if ($tag === null) {
                return null;
            }

            if ($tag === 'float') {
                $anyFloat = true;
            }
        }

        return $anyFloat ? 'float' : 'int';
    }

    private static function operandNumericTag(AbstractNode $arg): ?string
    {
        if ($arg instanceof LiteralNode) {
            $value = $arg->getValue();
            if (is_float($value)) {
                return 'float';
            }

            return is_int($value) ? 'int' : null;
        }

        $tag = self::normalisedTag(self::inferredTypeOfNode($arg));
        return in_array($tag, self::NUMERIC_PRIMITIVE_TAGS, true) ? $tag : null;
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
