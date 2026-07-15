<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

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

        $tempPath = $path . '.tmp.' . uniqid('', true);
        if (file_put_contents($tempPath, $content) === false) {
            trigger_error(
                sprintf('Phel cache: failed to write temp file "%s"', $tempPath),
                E_USER_WARNING,
            );
            return false;
        }

        if (!rename($tempPath, $path)) {
            trigger_error(
                sprintf('Phel cache: failed to rename "%s" to "%s"', $tempPath, $path),
                E_USER_WARNING,
            );
            @unlink($tempPath);
            return false;
        }

        return true;
    }
}
