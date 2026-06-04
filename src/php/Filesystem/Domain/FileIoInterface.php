<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

interface FileIoInterface
{
    /**
     * Returns true if the directory is writable by the current process.
     *
     * Abstraction over PHP's is_writable() so callers can be mocked in tests.
     */
    public function isWritable(string $tempDir): bool;
}
