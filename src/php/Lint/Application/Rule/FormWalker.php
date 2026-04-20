<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

/**
 * Shared depth-first traversal for rules that need to visit every nested
 * form, regardless of collection type. Callers pass a visitor callable
 * and may short-circuit by returning false.
 */
final class FormWalker
{
    /**
     * Visitor returns false to skip descending into the current form;
     * any other return value (including `null` / `void`) continues.
     *
     * @param callable(mixed):mixed $visit
     * @param \Phel\Lang\TypeInterface|mixed|null|scalar $form
     *
     * @psalm-param K|T|TValue|V|\Phel\Lang\TypeInterface|null|scalar $form
     */
    public static function walk(mixed $form, callable $visit): void
    {
        $proceed = $visit($form);
        if ($proceed === false) {
            return;
        }

        if ($form instanceof PersistentListInterface || $form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                self::walk($child, $visit);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                self::walk($k, $visit);
                self::walk($v, $visit);
            }
        }
    }
}
