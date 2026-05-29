<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Shared\CompilerConstants;

use function abs;
use function array_shift;
use function array_slice;
use function assert;
use function count;
use function implode;
use function in_array;
use function intdiv;
use function is_bool;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function max;
use function min;

/**
 * Compile-time evaluation of pure core fns whose arguments are all literal.
 *
 * Folding shrinks the AST surface — the resulting `LiteralNode` skips every
 * downstream pass (call-site cache, inline expansion, emission) — so the
 * scheme intentionally errs on the side of fewer false positives:
 *
 *  - The callee must be one of the whitelisted `phel.core` fns documented
 *    in {@see self::NUMERIC_REDUCERS} and friends. User-defined fns are
 *    never folded.
 *  - Every argument must already be a {@see LiteralNode} of a primitive
 *    numeric type (`int|float`). `BigInt`, `Ratio`, `BigDecimal`, strings
 *    and collection literals stay un-folded so their semantics route
 *    through the runtime numeric/equality dispatchers.
 *  - Operations that can raise at runtime (e.g. `/` by zero) are skipped
 *    so folding never converts a runtime exception into a compile-time
 *    one.
 *
 * Folding `if` is handled separately: it does not require the test to be a
 * core call, just a `LiteralNode`, so `(if true ...)` collapses to the
 * `then` branch without going through `InvokeSymbol` at all.
 */
final class ConstantFolder
{
    /**
     * Variadic numeric reducers. `+` and `*` define an identity element so
     * `(+)` and `(*)` are also foldable. `-` and `/` use one-arg semantics
     * that differ from a reducer and live in their own helpers.
     *
     * @var array<string, array{identity: int, op: 'add'|'mul'}>
     */
    private const array NUMERIC_REDUCERS = [
        '+' => ['identity' => 0, 'op' => 'add'],
        '*' => ['identity' => 1, 'op' => 'mul'],
    ];

    private const array BOOL_PREDICATES = ['not', 'nil?', 'true?', 'false?', 'boolean'];

    /**
     * Bitwise `php/` interop ops that `bit-and`, `bit-or`, `bit-xor`,
     * `bit-shift-left`, `bit-shift-right`, and `bit-not` inline-expand to.
     * Folding these covers both user-written `(php/& 12 10)` calls and the
     * `bit-and`/`bit-or`/… core fns whose `:inline` lowers to them.
     *
     * @var array<string, true>
     */
    private const array PHP_BITWISE_OPS = [
        '&' => true,
        '|' => true,
        '^' => true,
        '<<' => true,
        '>>' => true,
        '~' => true,
    ];

    /**
     * Whitelist of binary `phel.core` fns that can act as a `reduce`
     * reducer at compile time. Each maps `(acc, elt)` to a new acc using
     * the existing scalar `compute()` path, so a fold step that the
     * folder already refuses (e.g. divide-by-zero on `quot`) blocks the
     * whole reduction and the call stays in the runtime.
     *
     * Limited to numeric reducers whose two-arg variants do not produce
     * a boolean (so `=` / `<` / `<=` / `>` / `>=` are excluded — those
     * are pairwise predicates, not accumulating reducers).
     *
     * @var array<string, true>
     */
    private const array REDUCE_BINARY_OPS = [
        '+' => true,
        '*' => true,
        '-' => true,
        'min' => true,
        'max' => true,
        'mod' => true,
        'quot' => true,
        'rem' => true,
    ];

    public function fold(CallNode $node): ?AbstractNode
    {
        $fn = $node->getFn();

        if ($fn instanceof PhpVarNode && isset(self::PHP_BITWISE_OPS[$fn->getName()])) {
            $result = $this->foldBitwise($fn->getName(), $node->getArguments());
            if ($result === null) {
                return null;
            }

            return new LiteralNode($node->getEnv(), $result, $node->getStartSourceLocation());
        }

        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $fnName = $fn->getName()->getName();

        if (in_array($fnName, self::BOOL_PREDICATES, true)) {
            $result = $this->foldBoolPredicate($fnName, $node->getArguments());
            if ($result === null) {
                return null;
            }

            return new LiteralNode($node->getEnv(), $result, $node->getStartSourceLocation());
        }

        $accessorResult = $this->foldCollectionAccessor($fnName, $node);
        if ($accessorResult instanceof AbstractNode) {
            return $accessorResult;
        }

        if ($fnName === 'reduce') {
            $reduceResult = $this->foldReduce($node);
            if ($reduceResult instanceof AbstractNode) {
                return $reduceResult;
            }
        }

        if ($fnName === 'str') {
            $strResult = $this->foldStr($node->getArguments());
            if ($strResult !== null) {
                return new LiteralNode($node->getEnv(), $strResult, $node->getStartSourceLocation());
            }
        }

        $numbers = $this->extractNumericLiterals($node->getArguments());
        if ($numbers === null) {
            return null;
        }

        $result = $this->compute($fnName, $numbers);
        if ($result === null) {
            return null;
        }

        return new LiteralNode($node->getEnv(), $result, $node->getStartSourceLocation());
    }

    /**
     * Replaces an `IfNode` whose test is a literal with the surviving branch.
     * Phel truthiness: only `null` and `false` are falsy.
     */
    public function foldIf(IfNode $node): ?AbstractNode
    {
        $test = $node->getTestExpr();
        if (!$test instanceof LiteralNode) {
            return null;
        }

        $value = $test->getValue();
        $truthy = $value !== null && $value !== false;

        return $truthy ? $node->getThenExpr() : $node->getElseExpr();
    }

    /**
     * Compile-time evaluation of `count` / `first` / `last` / `nth` against
     * literal collection nodes:
     *
     *  - `(count [a b c])` and `(count {:k v})` and `(count #{a b})` always
     *    fold to the element-count literal.
     *  - `(first [...])` / `(last [...])` / `(nth [...] i)` only fold when
     *    every vector element is itself a `LiteralNode` (so substituting the
     *    target element cannot drop a side-effect).
     *  - Out-of-bounds `nth` keeps the call: Phel raises at runtime and we
     *    refuse to lift that exception to compile time.
     */
    private function foldCollectionAccessor(string $fnName, CallNode $node): ?AbstractNode
    {
        $args = $node->getArguments();

        if ($fnName === 'count') {
            return $this->foldCount($args, $node);
        }

        if ($fnName === 'first' || $fnName === 'last') {
            return $this->foldVectorPick($fnName, $args, $node);
        }

        if ($fnName === 'nth') {
            return $this->foldNth($args, $node);
        }

        return null;
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function foldCount(array $args, CallNode $node): ?LiteralNode
    {
        if (count($args) !== 1) {
            return null;
        }

        $arg = $args[0];
        $size = match (true) {
            $arg instanceof VectorNode => count($arg->getArgs()),
            $arg instanceof SetNode => count($arg->getValues()),
            $arg instanceof MapNode => intdiv(count($arg->getKeyValues()), 2),
            default => null,
        };

        if ($size === null) {
            return null;
        }

        return new LiteralNode($node->getEnv(), $size, $node->getStartSourceLocation());
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function foldVectorPick(string $fnName, array $args, CallNode $node): ?LiteralNode
    {
        if (count($args) !== 1) {
            return null;
        }

        $arg = $args[0];
        if (!$arg instanceof VectorNode) {
            return null;
        }

        $elements = $arg->getArgs();
        if (!$this->allLiteralNodes($elements)) {
            return null;
        }

        if ($elements === []) {
            // `(first [])` is `nil` in Phel; `(last [])` is `nil` too.
            return new LiteralNode($node->getEnv(), null, $node->getStartSourceLocation());
        }

        $pick = $fnName === 'first' ? $elements[0] : $elements[count($elements) - 1];
        assert($pick instanceof LiteralNode);

        return new LiteralNode($node->getEnv(), $pick->getValue(), $node->getStartSourceLocation());
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function foldNth(array $args, CallNode $node): ?LiteralNode
    {
        if (count($args) !== 2) {
            return null;
        }

        [$target, $index] = $args;
        if (!$target instanceof VectorNode || !$index instanceof LiteralNode) {
            return null;
        }

        $idx = $index->getValue();
        if (!is_int($idx) || $idx < 0) {
            return null;
        }

        $elements = $target->getArgs();
        if (!$this->allLiteralNodes($elements)) {
            return null;
        }

        if ($idx >= count($elements)) {
            // Out-of-bounds → Phel raises at runtime. Don't lift that.
            return null;
        }

        $pick = $elements[$idx];
        assert($pick instanceof LiteralNode);

        return new LiteralNode($node->getEnv(), $pick->getValue(), $node->getStartSourceLocation());
    }

    /**
     * @param list<AbstractNode> $nodes
     */
    private function allLiteralNodes(array $nodes): bool
    {
        return array_all($nodes, static fn($n): bool => $n instanceof LiteralNode);
    }

    /**
     * `(reduce f init coll)` with `f` a whitelisted pure binary
     * `phel.core` op, `init` a numeric literal, and `coll` a vector
     * literal whose elements are all numeric literals.
     *
     * Out of scope (kept as runtime calls):
     *  - the 2-arg `(reduce f coll)` variant — its empty-coll branch
     *    calls `(f)`, which only makes sense for ops with an identity
     *  - non-vector collections, `seq` / list / set literals
     *  - any `(reduced x)` early-termination value in the input — the
     *    fold computes step-by-step so it never observes one
     */
    private function foldReduce(CallNode $node): ?LiteralNode
    {
        $args = $node->getArguments();
        if (count($args) !== 3) {
            return null;
        }

        [$fnArg, $init, $coll] = $args;

        if (!$fnArg instanceof GlobalVarNode) {
            return null;
        }

        if ($fnArg->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $reducerName = $fnArg->getName()->getName();
        if (!isset(self::REDUCE_BINARY_OPS[$reducerName])) {
            return null;
        }

        if (!$init instanceof LiteralNode) {
            return null;
        }

        $accValue = $init->getValue();
        if (!is_int($accValue) && !is_float($accValue)) {
            return null;
        }

        if (!$coll instanceof VectorNode) {
            return null;
        }

        $elements = $coll->getArgs();
        if (!$this->allLiteralNodes($elements)) {
            return null;
        }

        if ($elements === []) {
            // `(reduce f init [])` is `init`. Materialise a fresh literal
            // so the source location maps to the reduce call, not the
            // init expr — keeps error reports accurate for downstream
            // passes that re-touch this node.
            return new LiteralNode($node->getEnv(), $accValue, $node->getStartSourceLocation());
        }

        foreach ($elements as $element) {
            assert($element instanceof LiteralNode);
            $eltValue = $element->getValue();
            if (!is_int($eltValue) && !is_float($eltValue)) {
                return null;
            }

            $stepResult = $this->compute($reducerName, [$accValue, $eltValue]);
            if ($stepResult === null || is_bool($stepResult)) {
                return null;
            }

            $accValue = $stepResult;
        }

        return new LiteralNode($node->getEnv(), $accValue, $node->getStartSourceLocation());
    }

    /**
     * `(str ...)` over literal `int` / `bool` / `string` / `nil` args.
     * Floats are skipped because Phel's `val-to-str` preserves trailing
     * `.0`, handles `NaN` / `±Infinity` specially, and we don't want to
     * duplicate that surface here.
     *
     * @param list<AbstractNode> $args
     */
    private function foldStr(array $args): ?string
    {
        $parts = [];
        foreach ($args as $arg) {
            if (!$arg instanceof LiteralNode) {
                return null;
            }

            $value = $arg->getValue();
            $part = match (true) {
                $value === null => '',
                $value === true => 'true',
                $value === false => 'false',
                is_int($value) => (string) $value,
                is_string($value) => $value,
                default => null,
            };

            if ($part === null) {
                return null;
            }

            $parts[] = $part;
        }

        return implode('', $parts);
    }

    /**
     * Bitwise fold over int literals. Skips when any arg is non-int
     * (PHP would coerce, but Phel core asserts int-only via
     * `assert-non-nil`), when shift amount is negative (runtime error
     * preserved), and when the op is `~` and the arity is not 1.
     *
     * @param list<AbstractNode> $args
     */
    private function foldBitwise(string $op, array $args): ?int
    {
        $ints = $this->extractIntLiterals($args);
        if ($ints === null) {
            return null;
        }

        if ($op === '~') {
            if (count($ints) !== 1) {
                return null;
            }

            return ~$ints[0];
        }

        if (count($ints) < 2) {
            return null;
        }

        if (($op === '<<' || $op === '>>') && $this->anyNegative(array_slice($ints, 1))) {
            return null;
        }

        $acc = $ints[0];
        $rest = array_slice($ints, 1);
        foreach ($rest as $n) {
            $acc = match ($op) {
                '&' => $acc & $n,
                '|' => $acc | $n,
                '^' => $acc ^ $n,
                '<<' => $acc << $n,
                '>>' => $acc >> $n,
                default => null,
            };

            if ($acc === null) {
                return null;
            }
        }

        return $acc;
    }

    /**
     * @param list<AbstractNode> $args
     *
     * @return list<int>|null
     */
    private function extractIntLiterals(array $args): ?array
    {
        $ints = [];
        foreach ($args as $arg) {
            if (!$arg instanceof LiteralNode) {
                return null;
            }

            $value = $arg->getValue();
            if (!is_int($value)) {
                return null;
            }

            $ints[] = $value;
        }

        return $ints;
    }

    /**
     * @param list<int> $values
     */
    private function anyNegative(array $values): bool
    {
        return array_any($values, static fn($v): bool => $v < 0);
    }

    /**
     * Single-arg boolean predicates over any literal value. Phel truthiness:
     * only `nil` and `false` are falsy; everything else (including `0`,
     * `""`, empty collections) is truthy.
     *
     * @param list<AbstractNode> $args
     */
    private function foldBoolPredicate(string $fnName, array $args): ?bool
    {
        if (count($args) !== 1) {
            return null;
        }

        $arg = $args[0];
        if (!$arg instanceof LiteralNode) {
            return null;
        }

        $value = $arg->getValue();

        return match ($fnName) {
            'not' => $value === null || $value === false,
            'nil?' => $value === null,
            'true?' => $value === true,
            'false?' => $value === false,
            'boolean' => $value !== null && $value !== false,
            default => null,
        };
    }

    /**
     * @param list<AbstractNode> $args
     *
     * @return list<float|int>|null
     */
    private function extractNumericLiterals(array $args): ?array
    {
        $literals = [];
        foreach ($args as $arg) {
            if (!$arg instanceof LiteralNode) {
                return null;
            }

            $value = $arg->getValue();
            if (!is_int($value) && !is_float($value)) {
                return null;
            }

            $literals[] = $value;
        }

        return $literals;
    }

    /**
     * The set of core fns this method can evaluate at compile time is the
     * vetted, effect-free list that {@see Simplification\SymbolicPurityDetector}'s
     * `PURE_CORE_FNS` mirrors (the detector applies them symbolically over
     * free variables, not only literals). Keep the two in sync when adding
     * a pure-but-not-foldable core fn.
     *
     * @param list<float|int> $literals
     */
    private function compute(string $fnName, array $literals): int|float|bool|null
    {
        if (isset(self::NUMERIC_REDUCERS[$fnName])) {
            return $this->reduce(self::NUMERIC_REDUCERS[$fnName], $literals);
        }

        return match ($fnName) {
            '-' => $this->minus($literals),
            'inc' => $this->shift($literals, +1),
            'dec' => $this->shift($literals, -1),
            // Phel `=` is type-strict: `(= 1 1.0)` is `false`. Numeric
            // promotion is reserved for `<` / `<=` / `>` / `>=` which
            // compare values, not identities.
            '=' => $this->compareAll($literals, static fn($a, $b): bool => $a === $b),
            'not=' => $this->negate($this->compareAll($literals, static fn($a, $b): bool => $a === $b)),
            '<' => $this->compareAll($literals, static fn($a, $b): bool => $a < $b),
            '<=' => $this->compareAll($literals, static fn($a, $b): bool => $a <= $b),
            '>' => $this->compareAll($literals, static fn($a, $b): bool => $a > $b),
            '>=' => $this->compareAll($literals, static fn($a, $b): bool => $a >= $b),
            'min' => $this->extremum($literals, true),
            'max' => $this->extremum($literals, false),
            'mod' => $this->modFloor($literals),
            'quot' => $this->quotTrunc($literals),
            'rem' => $this->remTrunc($literals),
            'abs' => $this->absolute($literals),
            default => null,
        };
    }

    /**
     * N-ary pairwise comparison. Phel mirrors Clojure: `(=)` is an arity
     * error (skip), `(= x)` is `true`, otherwise consecutive pairs must
     * all satisfy `cmp`.
     *
     * @param list<float|int>                      $literals
     * @param callable(float|int, float|int): bool $cmp
     */
    private function compareAll(array $literals, callable $cmp): ?bool
    {
        $count = count($literals);
        if ($count === 0) {
            return null;
        }

        for ($i = 0; $i < $count - 1; ++$i) {
            if (!$cmp($literals[$i], $literals[$i + 1])) {
                return false;
            }
        }

        return true;
    }

    private function negate(?bool $value): ?bool
    {
        return $value === null ? null : !$value;
    }

    /**
     * `min` / `max` over int / float literals. Skips empty arity (Phel
     * raises) and skips any `NaN` operand (Phel returns `##NaN` which
     * we cannot reliably reify as a literal).
     *
     * @param list<float|int> $literals
     */
    private function extremum(array $literals, bool $useMin): int|float|null
    {
        if ($literals === []) {
            return null;
        }

        foreach ($literals as $n) {
            if (is_float($n) && is_nan($n)) {
                return null;
            }
        }

        return $useMin ? min($literals) : max($literals);
    }

    /**
     * Phel `mod` is floor remainder: result has the sign of `divisor`.
     * `(mod -7 3)` → `2`. Divisor `0` skips so the runtime
     * `DivisionByZeroError` keeps its trigger point. Int-only — float
     * floor-remainder would need to mirror `NumericOperations::mod`
     * exactly.
     *
     * @param list<float|int> $literals
     */
    private function modFloor(array $literals): ?int
    {
        $pair = $this->twoIntPair($literals);
        if ($pair === null) {
            return null;
        }

        [$a, $b] = $pair;
        if ($b === 0) {
            return null;
        }

        $rem = $a % $b;
        if ($rem !== 0 && (($rem < 0) !== ($b < 0))) {
            return $rem + $b;
        }

        return $rem;
    }

    /**
     * Phel `quot` truncates toward zero. PHP `intdiv` matches.
     *
     * @param list<float|int> $literals
     */
    private function quotTrunc(array $literals): ?int
    {
        $pair = $this->twoIntPair($literals);
        if ($pair === null) {
            return null;
        }

        [$a, $b] = $pair;
        if ($b === 0) {
            return null;
        }

        return intdiv($a, $b);
    }

    /**
     * Phel `rem` has the sign of the dividend. PHP `%` matches.
     *
     * @param list<float|int> $literals
     */
    private function remTrunc(array $literals): ?int
    {
        $pair = $this->twoIntPair($literals);
        if ($pair === null) {
            return null;
        }

        [$a, $b] = $pair;
        if ($b === 0) {
            return null;
        }

        return $a % $b;
    }

    /**
     * `abs` on `PHP_INT_MIN` overflows to a float in PHP, which would
     * leak the runtime's `BigInt` promotion path through the fold —
     * skip so the runtime keeps control there.
     *
     * @param list<float|int> $literals
     */
    private function absolute(array $literals): int|float|null
    {
        if (count($literals) !== 1) {
            return null;
        }

        $value = $literals[0];

        if (is_int($value) && $value === PHP_INT_MIN) {
            return null;
        }

        return abs($value);
    }

    /**
     * @param list<float|int> $literals
     *
     * @return array{0: int, 1: int}|null
     */
    private function twoIntPair(array $literals): ?array
    {
        if (count($literals) !== 2) {
            return null;
        }

        [$a, $b] = $literals;
        if (!is_int($a) || !is_int($b)) {
            return null;
        }

        return [$a, $b];
    }

    /**
     * @param array{identity: int, op: 'add'|'mul'} $spec
     * @param list<float|int>                       $literals
     */
    private function reduce(array $spec, array $literals): int|float|null
    {
        $acc = $spec['identity'];
        foreach ($literals as $literal) {
            $acc = $spec['op'] === 'add'
                ? $this->add($acc, $literal)
                : $this->mul($acc, $literal);

            if ($acc === null) {
                return null;
            }
        }

        return $acc;
    }

    /**
     * `(- x)` negates, `(- x y z ...)` subtracts from `x`. `(-)` is a
     * runtime error, so it stays un-folded.
     *
     * @param list<float|int> $literals
     */
    private function minus(array $literals): int|float|null
    {
        if ($literals === []) {
            return null;
        }

        $head = array_shift($literals);
        if ($literals === []) {
            return -$head;
        }

        $acc = $head;
        foreach ($literals as $n) {
            $acc = $this->sub($acc, $n);
            if ($acc === null) {
                return null;
            }
        }

        return $acc;
    }

    /**
     * @param list<float|int> $literals
     */
    private function shift(array $literals, int $delta): int|float|null
    {
        if (count($literals) !== 1) {
            return null;
        }

        return $this->add($literals[0], $delta);
    }

    /**
     * Keep the int path when both operands are ints so the folded literal
     * preserves Phel's numeric type. PHP silently widens `int op int` to
     * `float` on overflow, but Phel's runtime `NumericOperations` promotes
     * to `BigInt` to preserve exactness — so we sample the result in float
     * space first and bail when it falls outside the PHP int range,
     * leaving the call for the runtime to handle. Mixed `int|float`
     * operands cast to float explicitly to satisfy psalm's strict
     * binary-operand mode.
     */
    private function add(int|float $a, int|float $b): int|float|null
    {
        if (is_int($a) && is_int($b)) {
            return $this->checkedInt((float) $a + (float) $b);
        }

        return (float) $a + (float) $b;
    }

    private function mul(int|float $a, int|float $b): int|float|null
    {
        if (is_int($a) && is_int($b)) {
            return $this->checkedInt((float) $a * (float) $b);
        }

        return (float) $a * (float) $b;
    }

    private function sub(int|float $a, int|float $b): int|float|null
    {
        if (is_int($a) && is_int($b)) {
            return $this->checkedInt((float) $a - (float) $b);
        }

        return (float) $a - (float) $b;
    }

    /**
     * Convert a float result back to int when it fits exactly in PHP's
     * int range. Otherwise bail so the runtime can promote to `BigInt`.
     */
    private function checkedInt(float $value): ?int
    {
        if ($value < PHP_INT_MIN || $value > PHP_INT_MAX) {
            return null;
        }

        $int = (int) $value;
        return ((float) $int === $value) ? $int : null;
    }
}
