<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;

/**
 * Shared helpers for rules that need to introspect the `(ns ...)` form.
 *
 * - `find` returns the first `(ns ...)` list in a source's top-level forms.
 * - `collectSymbolUses` accumulates every symbol name (and namespace) used
 *   anywhere in the source *outside* the `(ns ...)` form, so callers can
 *   decide whether a require/import is used at least once.
 */
final class NamespaceForm
{
    /**
     * @param list<mixed> $forms
     */
    public static function find(array $forms): ?PersistentListInterface
    {
        foreach ($forms as $form) {
            if (!$form instanceof PersistentListInterface) {
                continue;
            }

            if (count($form) === 0) {
                continue;
            }

            $head = $form->get(0);
            if ($head instanceof Symbol && $head->getName() === Symbol::NAME_NS) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $forms
     *
     * @return array<string, true>
     */
    public static function collectSymbolUses(array $forms, PersistentListInterface $nsForm): array
    {
        $seen = [];
        foreach ($forms as $form) {
            if ($form === $nsForm) {
                continue;
            }

            FormWalker::walk($form, static function (mixed $value) use (&$seen): void {
                if (!$value instanceof Symbol) {
                    return;
                }

                $seen[$value->getName()] = true;
                $ns = $value->getNamespace();
                if ($ns !== null && $ns !== '') {
                    $seen[$ns] = true;
                }
            });
        }

        return $seen;
    }
}
