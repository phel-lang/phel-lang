<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Filesystem\Domain\FileIoInterface;
use Phel\Shared\Exceptions\FileException;

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

        $this->ensureDirectoryExists($tempDir);

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

    /**
     * Creates the directory if it does not already exist.
     *
     * Idempotent: an already-existing directory (or one created concurrently
     * between the mkdir attempt and the is_dir re-check) is tolerated. umask is
     * reset to 0 around mkdir so the 0777 mode is applied as requested.
     *
     * @throws FileException if the directory cannot be created
     */
    private function ensureDirectoryExists(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            return;
        }

        $oldUmask = umask(0);
        if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw FileException::canNotCreateDirectory($tempDir);
        }

        umask($oldUmask);
        if (!is_dir($tempDir)) {
            throw FileException::canNotCreateDirectory($tempDir);
        }
    }
}
