<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Filesystem\Domain\FileIoInterface;
use Phel\Shared\Exceptions\FileException;

/**
 * Resolves the configured temp directory, creating it if missing and ensuring
 * it is writable.
 *
 * The resolved path is cached on the instance after the first successful call,
 * so subsequent calls return immediately without touching the filesystem.
 * Creation is idempotent (a concurrently-created directory is tolerated), and
 * a non-writable directory triggers a chmod 0777 retry before giving up with a
 * FileException.
 */
final class TempDirFinder
{
    private string $cachedTempDir = '';

    public function __construct(
        private readonly FileIoInterface $fileIo,
        private readonly string $configTempDir,
    ) {}

    /**
     * Returns the configured temporary directory. If it doesn't exist,
     * attempts to create it. Throws if creation fails.
     *
     * @throws FileException if the directory cannot be created
     */
    public function getOrCreateTempDir(): string
    {
        if ($this->cachedTempDir !== '') {
            return $this->cachedTempDir;
        }

        $tempDir = $this->configTempDir;

        if (!is_dir($tempDir)) {
            $oldUmask = umask(0);
            if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
                throw FileException::canNotCreateDirectory($tempDir);
            }

            umask($oldUmask);
            if (!is_dir($tempDir)) {
                throw FileException::canNotCreateDirectory($tempDir);
            }
        }

        // Directory exists but is not writable: try to broaden permissions
        // and re-check before failing.
        if (!$this->fileIo->isWritable($tempDir)) {
            @chmod($tempDir, 0777);

            // @phpstan-ignore-next-line if.alwaysFalse
            if ($this->fileIo->isWritable($tempDir)) {
                return $this->cachedTempDir = $tempDir;
            }

            throw FileException::directoryIsNotWritable($tempDir);
        }

        return $this->cachedTempDir = $tempDir;
    }
}
