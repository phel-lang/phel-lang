<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Shared\Api\ProjectIndex;

/**
 * @internal
 */
interface ProjectIndexerInterface
{
    /**
     * @param list<string> $srcDirs
     */
    public function index(array $srcDirs): ProjectIndex;
}
