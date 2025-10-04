<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

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

    /**
     * @return list<string>
     */
    public function replComplete(string $input): array;
}
