<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

/**
 * In-memory hand-off of compiled `(in-ns ...)` secondaries from the build-time
 * `(load ...)` evaluation to the {@see SecondaryFileHarvester}.
 *
 * Secondaries must be compiled while the primary's `(load ...)` runs, because
 * that is the only point the registry is warm in the right order (recompiling
 * one standalone afterwards re-runs macro expansion against a partial registry
 * and fails). When the persistent compiled-code cache is on, that cached `.php`
 * is what the harvester copies. With the cache off there is nowhere to keep the
 * compiled output, so this store holds it for the duration of the build instead
 * — keeping every `out/phel/core/*.php` sibling without a fragile recompile.
 */
final class CompiledSecondaryStore
{
    /** @var array<string, string> source path => compiled PHP (with preamble) */
    private array $compiledBySource = [];

    public function put(string $sourcePath, string $phpCode): void
    {
        $this->compiledBySource[$sourcePath] = $phpCode;
    }

    public function get(string $sourcePath): ?string
    {
        return $this->compiledBySource[$sourcePath] ?? null;
    }
}
