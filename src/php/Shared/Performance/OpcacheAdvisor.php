<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

use function sprintf;

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
    /**
     * @param string|null $iniTemplatePath Absolute path to a bundled
     *                                     `phel.ini` to point users at when
     *                                     the config is not optimal, or null
     *                                     when none is available.
     */
    public function advise(
        bool $opcacheLoaded,
        bool $enableCli,
        bool $fileCacheConfigured,
        ?string $iniTemplatePath = null,
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
            // file_cache must be an absolute path to a directory that already
            // exists, or PHP aborts at startup; warn explicitly so enabling it
            // does not trade one problem for a worse one.
            $messages[] = 'opcache.file_cache is not configured. Point it at an existing, writable, absolute directory (e.g. create /tmp/phel-opcache first, then opcache.file_cache=/tmp/phel-opcache) to persist the compiled cache across processes. PHP aborts at startup if the path is missing or relative, so keep it outside caches that "phel clear-cache" wipes.';
        }

        if ($messages === []) {
            return new OpcacheAdvice(true, [
                'OPcache CLI caching is fully configured.',
            ]);
        }

        if ($iniTemplatePath !== null) {
            $messages[] = sprintf(
                'A ready-to-use config ships at %s — run e.g. `php -c %s vendor/bin/phel run ...` (uncomment opcache.file_cache there first).',
                $iniTemplatePath,
                $iniTemplatePath,
            );
        }

        return new OpcacheAdvice(false, $messages);
    }
}
