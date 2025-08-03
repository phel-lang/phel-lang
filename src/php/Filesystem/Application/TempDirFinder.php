<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\Domain\FileIoInterface;

final class TempDirFinder
{
    private string $finalTempDir = '';

    public function __construct(
        private readonly FileIoInterface $fileIo,
        private readonly string $configTempDir,
    ) {
    }

    /**
     * Returns the configured temporary directory. If it doesn't exist,
     * attempts to create it. Throws if creation fails.
     *
     * @throws FileException if the directory cannot be created
     */
    public function getOrCreateTempDir(): string
    {
        if ($this->finalTempDir !== '') {
            return $this->finalTempDir;
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

        if (!$this->fileIo->isWritable($tempDir)) {
            @chmod($tempDir, 0777);

            if (!$this->fileIo->isWritable($tempDir)) {
                throw FileException::directoryIsNotWritable($tempDir);
            }
        }

        return $this->finalTempDir = $tempDir;
    }
}
