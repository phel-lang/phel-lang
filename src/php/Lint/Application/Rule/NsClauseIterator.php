<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Generator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;

use function count;

/**
 * Iterates the keyword-headed clauses inside a Phel `(ns ...)` form.
 *
 * Both `UnusedRequireRule` and `UnusedImportRule` (and any future rule
 * inspecting ns directives) need to walk child lists whose head is a
 * specific keyword — `:require`, `:use`, `:refer-macros`, etc. This
 * helper extracts that traversal so each rule only has to care about
 * the clause body.
 */
final class NsClauseIterator
{
    /**
     * Yield each child list of `$nsForm` whose first element is the
     * given keyword name (without the leading `:`).
     *
     * @return Generator<int, PersistentListInterface>
     */
    public static function clauses(PersistentListInterface $nsForm, string $keywordName): Generator
    {
        $size = count($nsForm);
        for ($i = 2; $i < $size; ++$i) {
            $child = $nsForm->get($i);
            if (!$child instanceof PersistentListInterface) {
                continue;
            }

            if (count($child) === 0) {
                continue;
            }

            $head = $child->get(0);
            if (!$head instanceof Keyword) {
                continue;
            }

            if ($head->getName() !== $keywordName) {
                continue;
            }

            yield $child;
        }
    }
}
