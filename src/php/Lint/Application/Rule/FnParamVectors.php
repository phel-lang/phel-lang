<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Generator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function count;

/**
 * Yields the parameter vector(s) declared inside an `(fn ...)`, `(defn ...)`
 * or variant — whether single-arity (one vector after the name) or
 * multi-arity (a series of nested `([params] body)` lists).
 *
 * Shared helper used by arity, shadowed-binding, and destructuring rules
 * so they stop re-implementing the same body walk.
 */
final class FnParamVectors
{
    /**
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function of(PersistentListInterface $fnForm): Generator
    {
        $size = count($fnForm);
        for ($i = 1; $i < $size; ++$i) {
            $child = $fnForm->get($i);

            if ($child instanceof PersistentVectorInterface) {
                yield $child;

                // Single-arity: the first vector after the name is the params.
                return;
            }

            if ($child instanceof PersistentListInterface && count($child) > 0) {
                $head = $child->get(0);
                if ($head instanceof PersistentVectorInterface) {
                    yield $head;
                }
            }
        }
    }
}
