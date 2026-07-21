<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\WritableDirectory;

use function dirname;
use function sprintf;

final class AtomicFileWriter
{
    public function write(string $path, string $content): bool
    {
        // Skip quietly when the cache dir is not writable (read-only sandbox):
        // a pre-warmed cache still serves reads, and only a genuine failure in
        // a writable dir (e.g. disk full, below) is worth a warning.
        if (!WritableDirectory::isUsable(dirname($path))) {
            return false;
        }

        // Gacela's primitive writes with LOCK_EX, treats a short byte count as
        // failure (a plain `=== false` misses a truncated disk-full write), and
        // invalidates opcache for the final path — needed because these files
        // are `require`d back (compiled code, env refers/aliases), so a stale
        // opcode entry would otherwise serve old content after a rewrite.
        if (!FileCache::writeContentsAtomically($path, $content)) {
            trigger_error(
                sprintf('Phel cache: failed to write "%s"', $path),
                E_USER_WARNING,
            );
            return false;
        }

        return true;
    }
}
