<?php

declare(strict_types=1);

namespace Phel\Api;

use Phel\Api\Transfer\PhelFunction;

interface ApiFacadeInterface
{
    /**
     * Get all public phel functions in the namespaces.
     *
     * @param list<string> $namespaces If empty then it will get all
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array;
}
