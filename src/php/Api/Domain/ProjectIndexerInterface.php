<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\ProjectIndex;

interface ProjectIndexerInterface
{
    /**
     * @param list<string> $srcDirs
     */
    public function index(array $srcDirs): ProjectIndex;
}
