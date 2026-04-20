<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\SymbolResolverInterface;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;

final readonly class SymbolResolver implements SymbolResolverInterface
{
    public function resolve(ProjectIndex $index, string $namespace, string $symbol): ?Definition
    {
        $key = SymbolKey::resolve($namespace, $symbol);

        if (isset($index->definitions[$key])) {
            return $index->definitions[$key];
        }

        // Fallback: search by unqualified name across namespaces — first match wins.
        foreach ($index->definitions as $def) {
            if ($def->name === $symbol) {
                return $def;
            }
        }

        return null;
    }
}
