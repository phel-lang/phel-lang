<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use function sprintf;

final class AtomicFileWriter
{
    public function write(string $path, string $content): bool
    {
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
