<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Shared\Api\PhelFunction;

/**
 * @internal
 */
interface PhelFnNormalizerInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array;
}
