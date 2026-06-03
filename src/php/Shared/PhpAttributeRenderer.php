<?php

declare(strict_types=1);

namespace Phel\Shared;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;

use function implode;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function str_replace;
use function var_export;

/**
 * Renders the `^{:php/attr [...]}` metadata value into PHP 8 attribute source.
 *
 * Each spec is a vector `[:Ns/Name & args]`:
 *
 *   [:Route]                       => #[\Route]
 *   [:ORM/Column]                  => #[\ORM\Column]
 *   [:Route "/p"]                  => #[\Route("/p")]
 *   [:ORM/Column {:length 255}]    => #[\ORM\Column(length: 255)]
 *   [:Route "/p" {:methods ["GET"]}] => #[\Route("/p", methods: ['GET'])]
 *
 * The class name is rendered fully-qualified (leading `\`) so generated PHP
 * needs no `use` imports. A namespaced keyword maps `.` to `\`, so
 * `:Symfony.Component.Routing.Attribute/Route` becomes
 * `\Symfony\Component\Routing\Attribute\Route`.
 *
 * Pure, stateless, no I/O.
 */
final class PhpAttributeRenderer
{
    /**
     * @return list<string> one `#[...]` line per spec; empty when no attributes
     */
    public function render(mixed $specs): array
    {
        if (!$specs instanceof PersistentVectorInterface) {
            return [];
        }

        $lines = [];
        foreach ($specs as $spec) {
            $line = $this->renderSpec($spec);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function renderSpec(mixed $spec): ?string
    {
        if (!$spec instanceof PersistentVectorInterface) {
            return null;
        }

        $name = null;
        $args = [];
        $index = 0;
        foreach ($spec as $element) {
            if ($index === 0) {
                $name = $element;
            } elseif ($element instanceof PersistentMapInterface) {
                foreach ($element as $key => $value) {
                    if ($key instanceof Keyword) {
                        $args[] = $key->getName() . ': ' . $this->renderValue($value);
                    }
                }
            } else {
                $args[] = $this->renderValue($element);
            }

            ++$index;
        }

        if (!$name instanceof Keyword) {
            return null;
        }

        $fqcn = $this->renderClassName($name);

        return $args === []
            ? '#[' . $fqcn . ']'
            : '#[' . $fqcn . '(' . implode(', ', $args) . ')]';
    }

    private function renderClassName(Keyword $name): string
    {
        $namespace = $name->getNamespace();
        if ($namespace === null || $namespace === '') {
            return '\\' . $name->getName();
        }

        return '\\' . str_replace('.', '\\', $namespace) . '\\' . $name->getName();
    }

    private function renderValue(mixed $value): string
    {
        if (is_string($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if ($value instanceof Keyword) {
            return var_export($value->getName(), true);
        }

        if ($value instanceof PersistentVectorInterface) {
            $items = [];
            foreach ($value as $item) {
                $items[] = $this->renderValue($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        if ($value instanceof PersistentMapInterface) {
            $items = [];
            foreach ($value as $key => $val) {
                $items[] = $this->renderValue($key) . ' => ' . $this->renderValue($val);
            }

            return '[' . implode(', ', $items) . ']';
        }

        return 'null';
    }
}
