<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

use Phel\Balance\Domain\Exception\BalanceSourceException;

/**
 * @internal
 */
interface FileCollectorInterface
{
    /**
     * @param list<string> $paths
     *
     * @throws BalanceSourceException when a listed directory cannot be walked
     *
     * @return list<string>
     */
    public function collect(array $paths): array;
}
