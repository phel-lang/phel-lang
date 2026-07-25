<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Shared\Api\Definition;
use Phel\Shared\Api\ProjectIndex;

interface SymbolResolverInterface
{
    public function resolve(ProjectIndex $index, string $namespace, string $symbol): ?Definition;
}
