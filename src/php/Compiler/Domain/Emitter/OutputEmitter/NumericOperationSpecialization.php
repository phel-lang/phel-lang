<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Lang\Keyword;

use function array_all;
use function array_any;
use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Call-site eligibility for `phel.core` arithmetic / comparison /
 * equality operators on statically-typed operands, which
 * {@see NodeEmitter\CallEmitter}
 * lowers to native PHP binary operators (`+`, `<`, `===`, …) instead of
 * the runtime numeric dispatch.
 *
 * Also owns {@see self::inferredTypeOfNode()} — the node→type-tag
 * inference used here for operand typing and reused by the string-concat
 * specialiser.
 */
final readonly class NumericOperationSpecialization
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

    /**
     * Single-arg `phel.core` increment / decrement wrappers whose dispatch
     * reduces to a native `($x + 1)` / `($x - 1)` when the operand is
     * statically proven `int` / `float`. Maps the Phel name to the PHP
     * operator the emitter splices before the literal `1`.
     *
     * @var array<string, string>
     */
    private const array INC_DEC_OPS = [
        'inc' => '+',
        'dec' => '-',
    ];

    /**
     * Arithmetic ops whose int result can overflow `PHP_INT_MAX` and promote
     * to `BigInt` at runtime. Comparison ops are excluded — they never overflow.
     *
     * @var list<string>
     */
    private const array ARITHMETIC_OPS = ['+', '-', '*'];

    /** @var list<string> */
    private const array NUMERIC_PRIMITIVE_TAGS = ['int', 'float'];

    /** @var list<string> */
    private const array EQUALITY_PRIMITIVE_TAGS = ['int', 'float', 'bool', 'string'];

    private function __construct() {}

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
        if (!PhelCoreCall::is($node, 'not')) {
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
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 2) {
            return null;
        }

        if (isset(self::NUMERIC_BINARY_OPS[$name])) {
            if (self::isOverflowProneLiteralArithmetic($name, $args)) {
                return null;
            }

            return self::bothArgsHavePrimitiveTag($args, self::NUMERIC_PRIMITIVE_TAGS)
                ? self::NUMERIC_BINARY_OPS[$name]
                : null;
        }

        if ($name === '=') {
            if (self::eitherArgIsKeywordLiteral($args)) {
                return '===';
            }

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
     * Single-arg `(inc x)` / `(dec x)` where `x` is a statically proven
     * primitive `int` / `float` operand. Returns the PHP operator to
     * splice before a literal `1` (`($x + 1)` / `($x - 1)`), or `null`
     * when the call is not specialisable.
     *
     * This is the 1-arg twin of the already-shipped `(+ ^int x 1)`
     * lowering: `inc`/`dec` are defined in `phel.core` as a
     * `NumericOperations::add`/`subtract` dispatch over a literal `1`, so
     * a tagged operand collapses to the same native op `(php/+ ^int x 1)`
     * already emits. The only divergence from the runtime — native `+`
     * promoting an overflowing `int` to `float` instead of `BigInt` — is
     * identical to that accepted policy.
     *
     * A **literal** operand is deliberately excluded: an int literal at
     * `PHP_INT_MAX` would overflow under native `+ 1` and diverge from the
     * runtime's `BigInt` promotion, exactly as a pure-literal `(+ ...)`
     * chain bails via {@see self::isOverflowProneLiteralArithmetic()}. A
     * nullable tag (`?int`) also fails the primitive-tag check, so the
     * moot `assert-non-nil` guard in the runtime defns can never matter.
     */
    public static function typedIncDecOp(CallNode $node): ?string
    {
        $name = PhelCoreCall::nameOf($node);
        if ($name === null || !isset(self::INC_DEC_OPS[$name])) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        $operand = $args[0];
        if ($operand instanceof LiteralNode) {
            return null;
        }

        $tag = TagNormalizer::normalise(self::inferredTypeOfNode($operand));
        return $tag !== null && in_array($tag, self::NUMERIC_PRIMITIVE_TAGS, true)
            ? self::INC_DEC_OPS[$name]
            : null;
    }

    public static function isTypedIncDec(CallNode $node): bool
    {
        return self::typedIncDecOp($node) !== null;
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
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) < 3) {
            return null;
        }

        if (!self::allArgsAreTaggedNumericLocals($args)) {
            return null;
        }

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
    public static function inferredTypeOfNode(AbstractNode $arg): ?string
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
     * `true` when at least one operand of a two-arg `=` is a compile-time
     * keyword literal. Keywords are interned singletons
     * ({@see Keyword::create()} returns the same object for equal ns/name),
     * and {@see \Phel\Lang\Equalizer::equals()} short-circuits on `===`, so:
     *
     *  - keyword vs keyword → equal keywords are `===`, unequal are not →
     *    native `===` is exact;
     *  - keyword vs non-keyword → `Equalizer::equals` returns false and PHP
     *    `===` of a `Keyword` object against any non-`Keyword` value is also
     *    false → identical result.
     *
     * So `($x === <hoisted-kw>)` is behaviour-preserving for *every* runtime
     * value of the other operand, regardless of its static type or tag — no
     * tag is required. Restricted to {@see Keyword} only: `Symbol` is not
     * interned, so `===` would diverge from `=` for equal-but-distinct
     * symbol objects.
     *
     * @param list<AbstractNode> $args
     */
    private static function eitherArgIsKeywordLiteral(array $args): bool
    {
        return array_any($args, static fn(AbstractNode $arg): bool => self::isKeywordLiteral($arg));
    }

    private static function isKeywordLiteral(AbstractNode $arg): bool
    {
        return $arg instanceof LiteralNode && $arg->getValue() instanceof Keyword;
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
     * Arithmetic (`+`, `-`, `*`) over two int literals only reaches the
     * emitter when the constant folder declined it — which, for int literals,
     * happens exactly when the native result overflows `PHP_INT_MAX`. Emitting
     * a native PHP op there would yield a `float`, diverging from the runtime's
     * `BigInt` promotion, so the call stays on the runtime dispatch. Mirrors
     * the literal exclusion already applied to {@see self::typedVariadicChain()}
     * for N>=3. Comparisons can't overflow and are left specialised.
     *
     * @param list<AbstractNode> $args
     */
    private static function isOverflowProneLiteralArithmetic(string $name, array $args): bool
    {
        if (!in_array($name, self::ARITHMETIC_OPS, true)) {
            return false;
        }

        return array_all(
            $args,
            static fn(AbstractNode $arg): bool => $arg instanceof LiteralNode && is_int($arg->getValue()),
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
                $tag = TagNormalizer::normalise(self::inferredTypeOfNode($arg));
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

        $tag = TagNormalizer::normalise(self::inferredTypeOfNode($arg));
        return $tag !== null && in_array($tag, $acceptedTags, true);
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
        $name = PhelCoreCall::nameOf($node);
        if ($name === null) {
            return null;
        }

        if (!in_array($name, self::ARITHMETIC_OPS, true)) {
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

        $tag = TagNormalizer::normalise(self::inferredTypeOfNode($arg));
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
