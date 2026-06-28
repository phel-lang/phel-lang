<?php

declare(strict_types=1);

namespace Phel\Shared;

use function md5;

/**
 * Cache key for a compiled Phel source file. The optimization level is mixed
 * in so entries compiled at different levels never collide; level 0 keeps the
 * historical plain `md5` so existing caches stay warm.
 *
 * Shared so the writer (build-time evaluator) and every reader (e.g. the
 * secondary-file harvester) key entries identically — a past drift between the
 * two silently dropped all `(load ...)` secondaries from `-O>0` builds.
 */
final class CompiledSourceHash
{
    public static function of(string $code, int $optimizationLevel): string
    {
        return $optimizationLevel > 0
            ? md5($code . '|O' . $optimizationLevel)
            : md5($code);
    }
}
