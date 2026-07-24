<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * @template TKey
 */
interface ContainsInterface
{
    /**
     * @param TKey $key
     */
    public function contains(mixed $key): bool;
}
