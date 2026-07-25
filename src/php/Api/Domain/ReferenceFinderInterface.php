<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;

interface ReferenceFinderInterface
{
    /**
     * @return list<Location>
     */
    public function find(ProjectIndex $index, string $namespace, string $symbol): array;
}
