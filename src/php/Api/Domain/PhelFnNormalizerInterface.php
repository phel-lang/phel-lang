<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\NormalizedPhelFunction;

interface PhelFnNormalizerInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(array $namespaces): array;
}
