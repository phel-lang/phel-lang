<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\PhelFunction;

interface PhelFnNormalizerInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return array<string,list<PhelFunction>>
     */
    public function getGroupedFunctions(array $namespaces = []): array;
}
