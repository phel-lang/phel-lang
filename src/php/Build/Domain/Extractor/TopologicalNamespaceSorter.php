<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

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
        /** @var array<string, true> $orderSet */
        $orderSet = [];
        /** @var list<string> $seenStack */
        $seenStack = [];
        /** @var array<string, true> $seenSet */
        $seenSet = [];

        foreach ($data as $item) {
            $this->process($item, $dependencies, $order, $orderSet, $seenStack, $seenSet);
        }

        return array_values($order);
    }

    /**
     * @param array<string, list<string>> $dependencies
     * @param string[] $order
     * @param array<string, true> $orderSet
     * @param list<string> $seenStack
     * @param array<string, true> $seenSet
     */
    private function process(
        string $item,
        array &$dependencies,
        array &$order,
        array &$orderSet,
        array &$seenStack,
        array &$seenSet,
    ): void {
        if (isset($seenSet[$item])) {
            throw new RuntimeException('Circular dependency detected, ' . implode(' -> ', [...$seenStack, $item]));
        }

        $seenSet[$item] = true;
        $seenStack[] = $item;

        if (isset($dependencies[$item])) {
            foreach ($dependencies[$item] as $master) {
                if (isset($dependencies[$master])) {
                    $this->process($master, $dependencies, $order, $orderSet, $seenStack, $seenSet);
                }

                if (!isset($orderSet[$master])) {
                    $order[] = $master;
                    $orderSet[$master] = true;
                }
            }
        }

        if (!isset($orderSet[$item])) {
            $order[] = $item;
            $orderSet[$item] = true;
        }

        array_pop($seenStack);
        unset($seenSet[$item]);
    }
}
