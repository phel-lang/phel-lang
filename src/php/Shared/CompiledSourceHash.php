<?php

declare(strict_types=1);

namespace Phel\Shared;

use function hash;

/**
 * Cache key for a compiled Phel source file. The optimization level and the
 * declared environment fingerprint are mixed in so entries compiled under
 * different inputs never collide. `xxh128` because a warm process hashes
 * every source it loads: on `phel.core` it was about 0.45 ms faster than
 * `md5`. Changing the algorithm changes every key, which is why the cache
 * index format moved with it.
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

        return hash('xxh128', $code . $suffix);
    }
}
