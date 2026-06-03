<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

/**
 * @extends AbstractFacade<FilesystemFactory>
 */
final class FilesystemFacade extends AbstractFacade implements FilesystemFacadeInterface
{
    /**
     * Registers a file for cleanup on the next clearAll() call.
     *
     * No-op when KEEP_GENERATED_TEMP_FILES is true (NullFilesystem strategy).
     */
    public function addFile(string $file): void
    {
        $this->getFactory()
            ->createFilesystem()
            ->addFile($file);
    }

    /**
     * Deletes all tracked files and resets the tracking state.
     *
     * No-op when KEEP_GENERATED_TEMP_FILES is true (NullFilesystem strategy).
     */
    public function clearAll(): void
    {
        $this->getFactory()
            ->createFilesystem()
            ->clearAll();
    }

    /**
     * Returns the temp directory path, creating and caching it if needed.
     */
    public function getTempDir(): string
    {
        return $this->getFactory()
            ->createTempDirFinder()
            ->getOrCreateTempDir();
    }

    public function getHealthCheck(): ModuleHealthCheckInterface
    {
        return $this->getFactory()->createTempDirHealthCheck();
    }
}
