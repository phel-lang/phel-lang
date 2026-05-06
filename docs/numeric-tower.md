# Numeric tower

Phel has a numeric tower built around four scalar shapes:

| Type | Where it comes from | Notes |
|------|---------------------|-------|
| `int` | Native PHP `int` | 64-bit signed on common platforms |
| `Phel\Lang\BigInteger` | `bigint`, `+'`, `*'`, etc. | Arbitrary-precision signed integer |
| `Phel\Lang\Rational` | `1/2` literals, `/`, `rationalize` | Always normalised; collapses to `int`/`BigInteger` when integral |
| `float` | Native PHP `float` (IEEE-754 double) | Inexact |

`+`, `-`, `*`, `/`, comparisons, and the predicates dispatch on these types via `Phel\Lang\NumericOperations`. PHP's native operators do not dispatch on objects, so the runtime helper does.

## Differences from Clojure

Phel diverges from Clojure's numeric tower in deliberate ways. Map your mental model:

### No `BigDecimal`

Phel does not have a base-10 floating-point type. For exact non-integer values use `Rational` (`1/3`, `(/ 1 3)`, `(rationalize 0.1)`). For inexact use `float`.

### No `1N` / `1M` literal suffixes

Clojure writes `1N` for `BigInt` and `1M` for `BigDecimal`. Phel has neither suffix. Promote with the explicit constructors:

```phel
(bigint 42)         ; => 42N (a BigInteger)
(bigint "1000000000000000000000")
(rationalize 0.1)   ; => 1/10
```

The promoting arithmetic ops (`+'`, `-'`, `*'`, `inc'`, `dec'`) return `BigInteger`.

### Large integer literals lex as `float`

```phel
12345678901234567890   ; => float (PHP int range overflow)
```

PHP's lexer parses oversize integer literals as `float`, so the same happens in Phel source. Wrap with `bigint` to keep precision:

```phel
(bigint "12345678901234567890")
```

### `bit-shift-right` is arithmetic

`bit-shift-right` performs an arithmetic (sign-preserving) shift, matching PHP's `>>`. There is currently no `unsigned-bit-shift-right`; use `(bit-and (bit-shift-right x n) mask)` if you need a logical shift.

### `==` arity-1 asserts numeric

Clojure's `(==)` and `(== x)` return `true` for any single argument. Phel's `==` requires its argument to be numeric and otherwise throws — single-argument `==` is treated as a numeric assertion, not an identity.

### `rationalize` uses shortest round-trip decimal

`(rationalize 0.1) ; => 1/10`, not `10000000000000001/100000000000000000`. The conversion picks the shortest decimal expansion that round-trips back to the same `float`, so binary-noise digits do not leak into the result. Floats whose exact value has no short decimal representation (e.g. `(/ 1.0 3.0)`) keep that round-trip representation as a `Rational`.

`(rationalize ##Inf)`, `(rationalize ##-Inf)`, and `(rationalize ##NaN)` throw `InvalidArgumentException`.

## Quick reference

| Need | Use |
|------|-----|
| Exact integer beyond PHP `int` | `(bigint "...")` or promoting ops `+'`, `*'` |
| Exact non-integer | `Rational` literal `1/2`, `(/ a b)`, `(rationalize x)` |
| Inexact decimal | native `float` |
| Predicate | `int?`, `bigint?`, `ratio?`, `float?`, `number?` |
| Numerator / denominator | `numerator`, `denominator` |
| Float to exact | `rationalize` |
