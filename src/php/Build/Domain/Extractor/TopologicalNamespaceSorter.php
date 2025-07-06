<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use RuntimeException;

final class TopologicalNamespaceSorter implements NamespaceSorterInterface
{
    /**
     * @param list<string> $data
     * @param array<string, list<string>> $dependencies
     *
     * @return list<string>
     */
    public function sort(array $data, array $dependencies): array
    {
        $order = [];
        $visited = [];
        $visiting = [];

        foreach ($data as $item) {
            $this->visit($item, $dependencies, $order, $visited, $visiting);
        }

        return $order;
    }

    /**
     * @param array<string, list<string>> $dependencies
     * @param array<string, bool> $visited
     * @param array<string, bool> $visiting
     */
    private function visit(
        string $item,
        array &$dependencies,
        array &$order,
        array &$visited,
        array &$visiting,
    ): void {
        if (isset($visited[$item])) {
            return;
        }

        if (isset($visiting[$item])) {
            throw new RuntimeException('Circular dependency detected: ' . $item);
        }

        $visiting[$item] = true;

        foreach ($dependencies[$item] ?? [] as $dep) {
            $this->visit($dep, $dependencies, $order, $visited, $visiting);
        }

        unset($visiting[$item]);
        $visited[$item] = true;
        $order[] = $item;
    }
}
