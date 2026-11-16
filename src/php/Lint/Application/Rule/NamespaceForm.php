<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Domain\FileAnalysis;

use function count;

/**
 * Shared helpers for rules that need to introspect the `(ns ...)` form.
 *
 * - `find` returns the first `(ns ...)` list in a source's top-level forms.
 * - `collectSymbolUses` accumulates every symbol name (and namespace) used
 *   anywhere in the source *outside* the `(ns ...)` form, so callers can
 *   decide whether a require/import is used at least once.
 * - `collectNamespaceSymbolUses` adds the forms `load` pulls into the
 *   namespace, or returns null when a load cannot be followed.
 *
 * @internal
 */
final class NamespaceForm
{
    /**
     * @param list<mixed> $forms
     *
     * @return PersistentListInterface<mixed>|null
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
     * @param PersistentListInterface<mixed> $nsForm
     *
     * @return array<string, true>|null
     */
    public static function collectNamespaceSymbolUses(FileAnalysis $analysis, PersistentListInterface $nsForm): ?array
    {
        if ($analysis->loadedForms === null) {
            return null;
        }

        return self::collectSymbolUses([...$analysis->forms, ...$analysis->loadedForms], $nsForm);
    }

    /**
     * @param list<mixed>                    $forms
     * @param PersistentListInterface<mixed> $nsForm
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

            self::collectSymbols($form, $seen);
        }

        return $seen;
    }

    /**
     * @param array<string, true> $seen
     */
    private static function collectSymbols(mixed $form, array &$seen): void
    {
        FormWalker::walk($form, static function (mixed $value) use (&$seen): void {
            if ($value instanceof PersistentHashSetInterface) {
                foreach ($value as $item) {
                    self::collectSymbols($item, $seen);
                }

                return;
            }

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
}
