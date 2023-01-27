<?php

declare(strict_types=1);

namespace Phel\Api;

use Phel\Api\Transfer\PhelFunction;

interface ApiFacadeInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return array<string,list<PhelFunction>>
     */
    public function getGroupedFunctions(array $namespaces = []): array;
}
