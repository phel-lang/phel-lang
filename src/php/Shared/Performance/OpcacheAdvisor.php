<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

/**
 * Evaluates whether OPcache is configured to persist the compiled-code cache
 * across CLI invocations, and produces actionable advice when it is not.
 *
 * Warm `phel run` reuses the compiled PHP stored under the Phel cache dir, but
 * without OPcache (and its file cache) every CLI process re-parses those files
 * from scratch, leaving a gap versus native PHP. This advisor is pure: callers
 * pass the relevant ini flags so it stays trivially testable.
 */
final class OpcacheAdvisor
{
    public function advise(
        bool $opcacheLoaded,
        bool $enableCli,
        bool $fileCacheConfigured,
    ): OpcacheAdvice {
        if (!$opcacheLoaded) {
            return new OpcacheAdvice(false, [
                'OPcache is not available. Enable the Zend OPcache extension to reuse compiled Phel between runs.',
            ]);
        }

        $messages = [];
        if (!$enableCli) {
            $messages[] = 'opcache.enable_cli is off. Set opcache.enable_cli=1 so the compiled Phel cache survives across CLI runs.';
        }

        if (!$fileCacheConfigured) {
            $messages[] = 'opcache.file_cache is not configured. Point it at a writable directory (e.g. opcache.file_cache=/tmp/phel-opcache) to persist the compiled cache across processes.';
        }

        if ($messages === []) {
            return new OpcacheAdvice(true, [
                'OPcache CLI caching is fully configured.',
            ]);
        }

        return new OpcacheAdvice(false, $messages);
    }
}
