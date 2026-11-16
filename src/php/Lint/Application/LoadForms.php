<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Rule\FormWalker;
use Phel\Shared\Munge;

use function in_array;
use function is_string;

/**
 * How lint finds the `(load ...)` forms of a namespace, shared by the rules'
 * reader and the cache so both follow the same files.
 *
 * @internal
 */
final class LoadForms
{
    /**
     * @return list<string|null> the literal path of each load, null for a computed one
     */
    public static function pathsIn(mixed $form): array
    {
        $paths = [];
        self::collectPaths($form, $paths);

        return $paths;
    }

    public static function switchesNamespace(mixed $form, string $namespace): bool
    {
        if (!$form instanceof PersistentListInterface) {
            return false;
        }

        $head = $form->first();
        if (!$head instanceof Symbol || !in_array($head->getFullName(), [Symbol::NAME_NS, Symbol::NAME_IN_NS], true)) {
            return false;
        }

        $target = $form->get(1);
        $targetName = match (true) {
            $target instanceof Symbol => $target->getFullName(),
            is_string($target) && $head->getFullName() === Symbol::NAME_IN_NS => $target,
            default => null,
        };

        return $targetName !== null && Munge::canonicalNs($targetName) !== Munge::canonicalNs($namespace);
    }

    /**
     * @param list<string|null> $paths
     */
    private static function collectPaths(mixed $form, array &$paths): void
    {
        FormWalker::walk($form, static function (mixed $node) use (&$paths): ?bool {
            if ($node instanceof PersistentHashSetInterface) {
                foreach ($node as $item) {
                    self::collectPaths($item, $paths);
                }

                return null;
            }

            if (!$node instanceof PersistentListInterface) {
                return null;
            }

            $head = $node->first();
            if (!$head instanceof Symbol) {
                return null;
            }

            if ($head->getFullName() === Symbol::NAME_QUOTE) {
                return false;
            }

            if ($head->getFullName() === Symbol::NAME_LOAD) {
                $argument = $node->get(1);
                $paths[] = is_string($argument) ? $argument : null;
            }

            return null;
        });
    }
}
