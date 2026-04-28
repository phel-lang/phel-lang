<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;

interface ReferenceFinderInterface
{
    /**
     * @return list<Location>
     */
    public function find(ProjectIndex $index, string $namespace, string $symbol): array;
}
