<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Filesystem\Domain\DirectoryWritabilityCheckerInterface;

/**
 * Thin adapter around PHP's is_writable() so permission checks can be
 * stubbed/mocked in tests instead of touching the real filesystem.
 *
 * @internal
 */
final class FileIo implements DirectoryWritabilityCheckerInterface
{
    public function isWritable(string $tempDir): bool
    {
        return is_writable($tempDir);
    }
}
