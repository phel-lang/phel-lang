<?php

declare(strict_types=1);

namespace Phel\Api;

use Phel\Api\Transfer\NormalizedPhelFunction;

interface ApiFacadeInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(array $namespaces = []): array;
}
