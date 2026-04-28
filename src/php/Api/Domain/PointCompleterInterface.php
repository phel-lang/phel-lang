<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\ProjectIndex;

interface PointCompleterInterface
{
    /**
     * @return list<Completion>
     */
    public function completeAtPoint(string $source, int $line, int $col, ProjectIndex $index): array;
}
