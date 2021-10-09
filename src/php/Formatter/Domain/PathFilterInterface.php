<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

interface PathFilterInterface
{
    /**
     * @param list<string> $paths
     *
     * @return list<string> The recursively unique valid paths to be formatted
     */
    public function filterPaths(array $paths): array;
}
