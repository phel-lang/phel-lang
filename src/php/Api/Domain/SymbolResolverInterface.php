<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;

interface SymbolResolverInterface
{
    public function resolve(ProjectIndex $index, string $namespace, string $symbol): ?Definition;
}
