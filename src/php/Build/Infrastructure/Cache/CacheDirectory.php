<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

/**
 * Owns the on-disk layout of the compiled-code cache: the root directory,
 * the `compiled/` subdirectory that holds every compiled and environment
 * file, and the index file that lists the entries.
 *
 * Creating `compiled/` with a recursive `mkdir` also guarantees the root
 * directory exists, so callers only ever need {@see ensure()}.
 */
final readonly class CacheDirectory
{
    public function __construct(
        private string $cacheDir,
    ) {}

    public function root(): string
    {
        return $this->cacheDir;
    }

    public function compiledDir(): string
    {
        return $this->cacheDir . '/compiled';
    }

    public function indexFile(): string
    {
        return $this->cacheDir . '/compiled-index.php';
    }

    public function ensure(): void
    {
        $compiledDir = $this->compiledDir();
        if (!is_dir($compiledDir)) {
            $oldUmask = umask(0);
            try {
                @mkdir($compiledDir, 0755, true);
            } finally {
                umask($oldUmask);
            }
        }
    }
}
