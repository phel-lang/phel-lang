<?php

declare(strict_types=1);

namespace Phel\Lang\Collections;

use function is_array;
use function is_float;

/**
 * Decides whether a keyed write would store exactly what the slot already
 * holds, so the collection can hand back itself instead of a copy (#3321).
 *
 * This is identity, as Clojure's `assoc` checks it, and deliberately not `=`:
 * a value that is equal but distinguishable must still be written. `=` would
 * keep an `int` where the caller wrote a `BigInt`, or keep a vector and drop
 * the metadata the new, equal one carries.
 *
 * `===` alone is one step too loose: PHP holds `0.0 === -0.0`, yet the two
 * are different values (`(/ 1 x)` is `INF` against `-INF`), so a zero is only
 * the same when its sign is. `NAN` is never `===` to itself and is always
 * written, which is safe.
 *
 * A PHP array is never the same, even when `===` holds. `===` compares arrays
 * element by element rather than by identity: two distinct recursive arrays
 * throw `Nesting level too deep`, and two arrays holding separate references
 * to equal values compare identical while writing one over the other still
 * changes what the slot points at. Checking `$new` alone is enough, since
 * `===` against a non-array is false without descending into anything.
 */
final class ValueIdentity
{
    public static function isSame(mixed $stored, mixed $new): bool
    {
        if (is_array($new)) {
            return false;
        }

        if (is_float($stored) && is_float($new)) {
            return $stored === $new && ($new !== 0.0 || fdiv(1.0, $stored) === fdiv(1.0, $new));
        }

        return $stored === $new;
    }
}
