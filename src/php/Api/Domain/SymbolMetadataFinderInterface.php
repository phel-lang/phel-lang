<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\PhelFunction;

interface SymbolMetadataFinderInterface
{
    /**
     * Resolve a symbol to its runtime metadata.
     *
     * Accepts bare names (resolved against `$currentNs`, then `phel.core`,
     * then any loaded namespace) and qualified forms using `/` as the
     * namespace separator (e.g. `user/greet`, `phel\core/map`,
     * `phel.core/map`).
     */
    public function find(string $symbol, string $currentNs = 'user'): ?PhelFunction;
}
