<?php

declare(strict_types=1);

namespace Phel\Shared\Binding;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;

use function count;
use function in_array;

/**
 * Parses the head vector of an iteration form. None of them is a `let`-style
 * list of name/value pairs, so reading one two-at-a-time mistakes a collection
 * expression for a bound name — at once a false "shadowed binding", a false
 * "unused binding", and a phantom completion candidate.
 *
 * Two grammars:
 *
 * - `for` / `dofor`: binding triples `binding :verb coll-expr` (`:in`,
 *   `:range`, `:keys`, `:pairs`), modifiers `:while expr`, `:when expr`,
 *   `:let [name expr ...]`, and the `:reduce [acc init]` option;
 * - `foreach`: `[value coll]` or `[key value coll]`, so the trailing element is
 *   the collection and everything before it is bound.
 */
final class IterationHead
{
    private const array FOR_FORMS = ['for', 'dofor'];

    private const string FOREACH_FORM = 'foreach';

    private const string REDUCE = 'reduce';

    private const string LET = 'let';

    public static function isIterationForm(string $name): bool
    {
        return $name === self::FOREACH_FORM || in_array($name, self::FOR_FORMS, true);
    }

    /**
     * Every name the head binds, in source order, each paired with the head
     * forms in which a reference to it counts as a use.
     *
     * `usageForms` deliberately excludes the entry's own collection/init
     * expression: `x` in `[x :in x]` refers to the outer `x`, not the new one.
     *
     * @param PersistentVectorInterface<mixed> $head
     *
     * @return list<array{binding: mixed, usageForms: list<mixed>}>
     */
    public static function entries(string $formName, PersistentVectorInterface $head): array
    {
        if ($formName === self::FOREACH_FORM) {
            return self::foreachEntries($head);
        }

        return self::forEntries($head);
    }

    /**
     * @param PersistentVectorInterface<mixed> $head
     *
     * @return list<array{binding: mixed, usageForms: list<mixed>}>
     */
    private static function forEntries(PersistentVectorInterface $head): array
    {
        $entries = [];
        $size = count($head);

        for ($i = 0; $i < $size;) {
            $first = $head->get($i);

            if (!$first instanceof Keyword) {
                // `binding :verb coll-expr`
                $entries[] = [
                    'binding' => $first,
                    'usageForms' => self::tailFrom($head, $i + 3, $size),
                ];
                $i += 3;

                continue;
            }

            $argument = $i + 1 < $size ? $head->get($i + 1) : null;
            $tail = self::tailFrom($head, $i + 2, $size);

            if ($first->getName() === self::REDUCE && $argument instanceof PersistentVectorInterface && count($argument) > 0) {
                $entries[] = [
                    'binding' => $argument->get(0),
                    'usageForms' => $tail,
                ];
            } elseif ($first->getName() === self::LET && $argument instanceof PersistentVectorInterface) {
                foreach (self::letEntries($argument, $tail) as $entry) {
                    $entries[] = $entry;
                }
            }

            // `:while` / `:when` / unknown modifiers bind nothing.
            $i += 2;
        }

        return $entries;
    }

    /**
     * Nothing else in a `foreach` head can reference what it binds, so every
     * entry's `usageForms` is empty.
     *
     * @param PersistentVectorInterface<mixed> $head
     *
     * @return list<array{binding: mixed, usageForms: list<mixed>}>
     */
    private static function foreachEntries(PersistentVectorInterface $head): array
    {
        $entries = [];
        $bound = count($head) - 1;

        for ($i = 0; $i < $bound; ++$i) {
            $entries[] = [
                'binding' => $head->get($i),
                'usageForms' => [],
            ];
        }

        return $entries;
    }

    /**
     * A `:let [a 1 b (inc a)]` modifier binds `let`-style pairs, and an earlier
     * name may be referenced by a later value inside the very same vector.
     *
     * @param PersistentVectorInterface<mixed> $bindings
     * @param list<mixed>                      $tail
     *
     * @return list<array{binding: mixed, usageForms: list<mixed>}>
     */
    private static function letEntries(PersistentVectorInterface $bindings, array $tail): array
    {
        $entries = [];
        $size = count($bindings);

        for ($i = 0; $i < $size; $i += 2) {
            $usageForms = $tail;
            for ($j = $i + 3; $j < $size; $j += 2) {
                $usageForms[] = $bindings->get($j);
            }

            $entries[] = [
                'binding' => $bindings->get($i),
                'usageForms' => $usageForms,
            ];
        }

        return $entries;
    }

    /**
     * @param PersistentVectorInterface<mixed> $head
     *
     * @return list<mixed>
     */
    private static function tailFrom(PersistentVectorInterface $head, int $from, int $size): array
    {
        $tail = [];
        for ($i = $from; $i < $size; ++$i) {
            $tail[] = $head->get($i);
        }

        return $tail;
    }
}
