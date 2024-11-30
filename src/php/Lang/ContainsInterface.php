<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * @template K
 */
interface ContainsInterface
{
    /**
     * @param K $key
     */
    public function contains(mixed $key): bool;
}
