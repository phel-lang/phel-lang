<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\MetaInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Domain\FileAnalysis;

use function count;
use function is_string;
use function ltrim;
use function preg_split;
use function str_ends_with;
use function strlen;
use function substr;
use function trim;

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
    public static function collectNamespaceSymbolUses(FileAnalysis $analysis, PersistentListInterface $nsForm, bool $classReferences = false): ?array
    {
        if ($analysis->loadedForms === null) {
            return null;
        }

        return self::collectSymbolUses([...$analysis->forms, ...$analysis->loadedForms], $nsForm, $classReferences);
    }

    /**
     * @param list<mixed>                    $forms
     * @param PersistentListInterface<mixed> $nsForm
     *
     * @return array<string, true>
     */
    public static function collectSymbolUses(array $forms, PersistentListInterface $nsForm, bool $classReferences = false): array
    {
        $seen = [];
        foreach ($forms as $form) {
            if ($form === $nsForm) {
                continue;
            }

            self::collectSymbols($form, $seen, $classReferences);
        }

        return $seen;
    }

    /**
     * `$classReferences` adds the places only a class name goes: a type tag
     * (`^Foo x`, `^"?Foo|int"`) and the head of a constructor call `(Foo. x)`.
     * Unused-require leaves them out, or `^string` would hide an unused
     * `(:require phel.string)`.
     *
     * @param array<string, true> $seen
     */
    private static function collectSymbols(mixed $form, array &$seen, bool $classReferences): void
    {
        FormWalker::walk($form, static function (mixed $value) use (&$seen, $classReferences): void {
            if ($classReferences) {
                self::collectClassReferences($value, $seen);
            }

            if ($value instanceof PersistentHashSetInterface) {
                foreach ($value as $item) {
                    self::collectSymbols($item, $seen, $classReferences);
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

    /**
     * @param array<string, true> $seen
     */
    private static function collectClassReferences(mixed $value, array &$seen): void
    {
        $meta = $value instanceof MetaInterface ? $value->getMeta() : null;
        if ($meta instanceof PersistentMapInterface) {
            $tag = $meta->find(Keyword::create('tag'));
            $tagName = $tag instanceof Symbol ? $tag->getName() : $tag;
            if (is_string($tagName)) {
                foreach (preg_split('/[|&]/', $tagName) ?: [] as $member) {
                    $seen[ltrim(trim($member), '?')] = true;
                }
            }
        }

        if ($value instanceof PersistentListInterface && count($value) > 0) {
            $head = $value->first();
            $name = $head instanceof Symbol ? $head->getName() : '';
            if (strlen($name) > 1 && str_ends_with($name, '.')) {
                $seen[substr($name, 0, -1)] = true;
            }
        }
    }
}
