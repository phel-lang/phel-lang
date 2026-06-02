<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;

use function abs;
use function array_shift;
use function count;
use function intdiv;
use function is_float;
use function is_int;
use function is_nan;
use function max;
use function min;

/**
 * Compile-time evaluation of scalar `phel.core` arithmetic and comparison
 * fns over numeric literals: `+ - * inc dec = not= < <= > >= min max mod
 * quot rem abs`. Returns `null` whenever folding could change semantics —
 * non-numeric args, overflow past PHP's int range (where the runtime would
 * promote to `BigInt`), divide-by-zero, `NaN` operands, and arity errors.
 *
 * The set of fns evaluated here is the vetted, effect-free list that
 * {@see Simplification\SymbolicPurityDetector}'s `PURE_CORE_FNS` mirrors.
 * Keep the two in sync when adding a pure-but-not-foldable core fn.
 */
final readonly class LiteralArithmeticFolder
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

    /**
     * Extracts numeric literals from the call args and evaluates `$fnName`
     * over them. Returns `null` when any arg is not a numeric literal or the
     * operation is not foldable.
     *
     * @param list<AbstractNode> $args
     */
    public function fold(string $fnName, array $args): int|float|bool|null
    {
        $numbers = $this->extractNumericLiterals($args);
        if ($numbers === null) {
            return null;
        }

        return $this->compute($fnName, $numbers);
    }

    /**
     * Evaluates `$fnName` over already-extracted numeric literals. Exposed so
     * {@see LiteralCollectionFolder} can drive `reduce` step-by-step through
     * the same scalar path.
     *
     * @param list<float|int> $literals
     */
    public function compute(string $fnName, array $literals): int|float|bool|null
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
