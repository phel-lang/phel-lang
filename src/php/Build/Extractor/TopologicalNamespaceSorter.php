<?php

declare(strict_types=1);

namespace Phel\Build\Extractor;

use RuntimeException;

final class TopologicalNamespaceSorter implements NamespaceSorterInterface
{
    /**
     * @param list<string> $data the data array that should be sorted
     * @param array<string, list<string>> $dependencies a map of dependencies for each data node
     *
     * @return list<string> The sorted array
     */
    public function sort(array $data, array $dependencies): array
    {
        /** @var list<string> $order */
        $order = [];
        /** @var list<string> $seen */
        $seen = [];
        foreach ($data as $item) {
            $this->process($item, $dependencies, $order, $seen);
        }

        return $order;
    }

    /**
     * @param string $item
     * @param array<string, list<string>> $dependencies
     * @param string[] $order
     * @param array<int, string> $seen
     */
    private function process(string $item, array &$dependencies, array &$order, array &$seen): void
    {
        if (in_array($item, $seen)) {
            throw new RuntimeException('Circular dependency detected, ' . implode(' -> ', [...$seen, $item]));
        }

        $seen[] = $item;
        if (isset($dependencies[$item])) {
            foreach ($dependencies[$item] as $master) {
                if (isset($dependencies[$master])) {
                    $this->process($master, $dependencies, $order, $seen);
                }

                if (!in_array($master, $order, true)) {
                    $order[] = $master;
                }

                $index = array_search($master, $seen, true);
                if ($index !== false) {
                    unset($seen[$index]);
                }
            }
        }

        if (!in_array($item, $order, true)) {
            $order[] = $item;
        }

        $index = array_search($item, $seen, true);
        if ($index !== false) {
            unset($seen[$index]);
        }
    }
}
