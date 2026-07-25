<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;

use function count;
use function in_array;

/**
 * Parses the head vector of a `(for [...] body)` / `(dofor [...] body)` form.
 *
 * The head is NOT a `let`-style list of name/value pairs. It is a sequence of:
 *
 * - binding triples `binding :verb coll-expr` (`:in`, `:range`, `:keys`, `:pairs`),
 * - modifiers `:while expr`, `:when expr`, `:let [name expr ...]`,
 * - options `:reduce [acc init]`.
 *
 * Reading it two-at-a-time (as `let` rules do) mistakes `coll-expr` for a bound
 * name, which is both a false "shadowed binding" and a false "unused binding".
 */
final class ForHead
{
    private const array FORM_NAMES = ['for', 'dofor'];

    private const string REDUCE = 'reduce';

    private const string LET = 'let';

    public static function isForForm(string $name): bool
    {
        return in_array($name, self::FORM_NAMES, true);
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
    public static function entries(PersistentVectorInterface $head): array
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
