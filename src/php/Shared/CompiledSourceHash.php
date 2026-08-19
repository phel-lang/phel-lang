<?php

declare(strict_types=1);

namespace Phel\Shared;

use function md5;

/**
 * Cache key for a compiled Phel source file. The optimization level and the
 * declared environment fingerprint are mixed in so entries compiled under
 * different inputs never collide; a level-0 compile with no declared
 * environment keeps the historical plain `md5` so existing caches stay warm.
 *
 * Shared so the writer (build-time evaluator) and every reader (e.g. the
 * secondary-file harvester) key entries identically — a past drift between the
 * two silently dropped all `(load ...)` secondaries from `-O>0` builds.
 */
final class CompiledSourceHash
{
    public static function of(string $code, int $optimizationLevel, string $envFingerprint = ''): string
    {
        $suffix = $optimizationLevel > 0 ? '|O' . $optimizationLevel : '';
        if ($envFingerprint !== '') {
            $suffix .= '|E' . $envFingerprint;
        }

        return md5($code . $suffix);
    }
}
