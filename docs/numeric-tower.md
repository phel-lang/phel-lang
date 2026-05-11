# Numeric tower

Phel has a numeric tower built around five scalar shapes:

| Type | Where it comes from | Notes |
|------|---------------------|-------|
| `int` | Native PHP `int` | 64-bit signed on common platforms |
| `Phel\Lang\BigInteger` | `bigint`, `+'`, `*'`, etc. | Arbitrary-precision signed integer |
| `Phel\Lang\Rational` | `1/2` literals, `/`, `rationalize` | Always normalised; collapses to `int`/`BigInteger` when integral |
| `Phel\Lang\BigDecimal` | `1.5M` literal, `bigdec` | Arbitrary-precision exact decimal |
| `float` | Native PHP `float` (IEEE-754 double) | Inexact |

`+`, `-`, `*`, `/`, comparisons, and the predicates dispatch on these types via `Phel\Lang\NumericOperations`. PHP's native operators do not dispatch on objects, so the runtime helper does.

## Notes

### Exact decimal literals: `1.5M`

`M`-suffixed numerals read as `BigDecimal`. Use for monetary values and any computation where binary float drift is unacceptable.

```phel
1.5M                ; => 1.5M (a BigDecimal)
(+ 0.1M 0.2M)       ; => 0.3M (no float drift)
(bigdec "3.14159")  ; => 3.14159M
(bigdec? 1.5M)      ; => true
```

### Promoting integers: `bigint` and `+'`

Promote with the explicit constructors:

```phel
(bigint 42)         ; => 42N (a BigInteger)
(bigint "1000000000000000000000")
(rationalize 0.1)   ; => 1/10
```

The promoting arithmetic ops (`+'`, `-'`, `*'`, `inc'`, `dec'`) auto-promote to `BigInteger` on overflow, so use them whenever overflow is possible:

```phel
(*' 1000000000 1000000000 1000000000)  ; => big enough to overflow PHP int
(inc' 9223372036854775807)             ; => 9223372036854775808N
```

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

Clojure's `(==)` and `(== x)` return `true` for any single argument. Phel's `==` requires its argument to be numeric and otherwise throws. Single-argument `==` is a numeric assertion, not an identity.

### `rationalize` uses shortest round-trip decimal

`(rationalize 0.1) ; => 1/10`, not `10000000000000001/100000000000000000`. The conversion picks the shortest decimal expansion that round-trips back to the same `float`, so binary-noise digits do not leak into the result. Floats whose exact value has no short decimal representation (e.g. `(/ 1.0 3.0)`) keep that round-trip representation as a `Rational`.

`(rationalize ##Inf)`, `(rationalize ##-Inf)`, and `(rationalize ##NaN)` throw `InvalidArgumentException`.

## Quick reference

| Need | Use |
|------|-----|
| Exact integer beyond PHP `int` | `(bigint "...")` or promoting ops `+'`, `*'` |
| Exact non-integer ratio | `Rational` literal `1/2`, `(/ a b)`, `(rationalize x)` |
| Exact decimal (money, etc.) | `BigDecimal` literal `1.5M`, `(bigdec "...")` |
| Inexact decimal | native `float` |
| Predicate | `int?`, `bigint?`, `ratio?`, `bigdec?`, `decimal?`, `float?`, `number?` |
| Numerator / denominator | `numerator`, `denominator` |
| Float to exact | `rationalize` |

## See also

- [Clojure Migration](clojure-migration.md): numeric differences
- Core predicates: `int?`, `bigint?`, `ratio?`, `bigdec?`
