<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Filesystem\Domain\FileIoInterface;

/**
 * Thin adapter around PHP's is_writable() so permission checks can be
 * stubbed/mocked in tests instead of touching the real filesystem.
 */
final class FileIo implements FileIoInterface
{
    public function isWritable(string $tempDir): bool
    {
        return is_writable($tempDir);
    }
}
