<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use RuntimeException;

use function sprintf;

final class TempDirFinder
{
    private string $finalTempDir = '';

    public function __construct(
        private readonly string $configTempDir,
    ) {
    }

    /**
     * Returns the configured temporary directory. If it doesn't exist,
     * attempts to create it. Throws if creation fails.
     *
     * @throws RuntimeException if the directory cannot be created
     */
    public function getOrCreateTempDir(): string
    {
        if ($this->finalTempDir !== '') {
            return $this->finalTempDir;
        }

        $tempDir = $this->configTempDir;

        if (!is_dir($tempDir) && !@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Unable to create temporary directory: "%s"', $tempDir));
        }

        return $this->finalTempDir = $tempDir;
    }
}
