<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use RuntimeException;

use function array_slice;

/**
 * @internal
 */
final class TopologicalNamespaceSorter implements NamespaceSorterInterface
{
    /**
     * @param list<string>                $data
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
     * @param list<string>                $order
     * @param array<string, bool>         $visited
     * @param array<string, bool>         $visiting
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
            throw new RuntimeException('Circular dependency detected: ' . $this->renderCycle($item, $visiting));
        }

        $visiting[$item] = true;

        foreach ($dependencies[$item] ?? [] as $dep) {
            $this->visit($dep, $dependencies, $order, $visited, $visiting);
        }

        unset($visiting[$item]);
        $visited[$item] = true;
        $order[] = $item;
    }

    /**
     * Renders the whole cycle (`a -> b -> a`), not just the namespace the
     * walk happened to re-enter. Naming one node leaves the user to rebuild
     * the require chain by hand across every file in the project.
     *
     * `$visiting` is the current DFS stack in insertion order, so the cycle
     * is its tail starting at `$item`.
     *
     * @param array<string, bool> $visiting
     */
    private function renderCycle(string $item, array $visiting): string
    {
        $stack = array_keys($visiting);
        $start = array_search($item, $stack, true);
        $cycle = $start === false ? [$item] : array_slice($stack, $start);
        $cycle[] = $item;

        return implode(' -> ', $cycle);
    }
}
