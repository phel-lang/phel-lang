<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

use Generator;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\IteratorUnwrapper;
use Traversable;

use function is_array;
use function is_object;
use function method_exists;

/**
 * Shared element-by-element equality for lazy sequences. `LazySeq`,
 * `ChunkedSeq` and `Cons` all compared their elements with the same lockstep
 * walk; this collapses the three copies (two verbatim, one inlined) into one
 * place so a change to the accepted shapes or the divergence rule lands once.
 */
final readonly class LazySeqEquality
{
    private function __construct() {}

    /**
     * Converts an arbitrary value into a lazy iterator, or `null` when the
     * value is not sequence-like. Lets equality compare element-by-element
     * without realizing infinite lazy sequences.
     *
     * @return Generator<int, mixed>|null
     */
    public static function iteratorFor(mixed $value): ?Generator
    {
        if ($value instanceof Traversable) {
            return (static function () use ($value): Generator {
                foreach ($value as $v) {
                    yield $v;
                }
            })();
        }

        if (is_array($value)) {
            return (static function () use ($value): Generator {
                foreach ($value as $v) {
                    yield $v;
                }
            })();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            $array = $value->toArray();
            return (static function () use ($array): Generator {
                foreach ($array as $v) {
                    yield $v;
                }
            })();
        }

        return null;
    }

    /**
     * Walks two iterables in lockstep, comparing element-by-element via the
     * equalizer. Returns `false` on the first divergence (a length mismatch
     * or an unequal element) and `true` only if both exhaust at the same
     * point.
     *
     * Running this against two infinite sequences loops forever, matching
     * Clojure's behavior — it is the caller's responsibility to avoid
     * comparing two infinite sequences for equality.
     *
     * @param Traversable<mixed> $left
     * @param Traversable<mixed> $right
     */
    public static function walkPairwise(Traversable $left, Traversable $right, EqualizerInterface $equalizer): bool
    {
        $leftIter = IteratorUnwrapper::unwrap($left);
        $rightIter = IteratorUnwrapper::unwrap($right);

        $leftIter->rewind();
        $rightIter->rewind();

        while (true) {
            $leftValid = $leftIter->valid();
            $rightValid = $rightIter->valid();
            if ($leftValid !== $rightValid) {
                return false;
            }

            if (!$leftValid) {
                return true;
            }

            if (!$equalizer->equals($leftIter->current(), $rightIter->current())) {
                return false;
            }

            $leftIter->next();
            $rightIter->next();
        }
    }
}
