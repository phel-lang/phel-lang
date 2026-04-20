<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\ReferenceFinderInterface;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;

final readonly class ReferenceFinder implements ReferenceFinderInterface
{
    /**
     * @return list<Location>
     */
    public function find(ProjectIndex $index, string $namespace, string $symbol): array
    {
        $key = $symbol;
        if (!str_contains($symbol, '/')) {
            $key = $namespace === '' ? $symbol : $namespace . '/' . $symbol;
        }

        $locations = $index->references[$key] ?? [];

        // Also try unqualified lookup for same-file references
        if ($locations === [] && str_contains($key, '/')) {
            $short = substr($key, (int) strrpos($key, '/') + 1);
            $locations = $index->references[$short] ?? [];
        }

        return $locations;
    }
}
