<?php

declare(strict_types=1);

namespace Phel\Build\Extractor;

interface NamespaceSorterInterface
{
    /**
     * @param list<string> $data the data array that should be sorted
     * @param array<string, list<string>> $dependencies a map of dependencies for each data node
     *
     * @return list<string> The sorted array
     */
    public function sort(array $data, array $dependencies): array;
}
