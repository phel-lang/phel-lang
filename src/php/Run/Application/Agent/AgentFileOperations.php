<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use RuntimeException;

use function is_dir;
use function sprintf;

/**
 * Every filesystem write `agent-install` performs, with its return value
 * checked. A failed `copy()` used to be reported to the user as a successful
 * install, which is the worst outcome for a command whose whole job is putting
 * files where someone expects to find them.
 *
 * @internal
 */
final class AgentFileOperations
{
    public static function copy(string $source, string $target): void
    {
        if (!copy($source, $target)) {
            throw new RuntimeException(sprintf('Cannot copy %s to %s', $source, $target));
        }
    }

    public static function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Cannot write file: %s', $path));
        }
    }

    public static function rename(string $from, string $to): void
    {
        if (!rename($from, $to)) {
            throw new RuntimeException(sprintf('Cannot move %s to %s', $from, $to));
        }
    }

    public static function delete(string $path): void
    {
        if (!unlink($path)) {
            throw new RuntimeException(sprintf('Cannot delete file: %s', $path));
        }
    }

    public static function deleteDirectory(string $dir): void
    {
        if (!rmdir($dir)) {
            throw new RuntimeException(sprintf('Cannot delete directory: %s', $dir));
        }
    }

    public static function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dir));
        }
    }
}
