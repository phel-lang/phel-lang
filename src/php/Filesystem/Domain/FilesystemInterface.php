<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

/**
 * Tracks compiled artifacts for later cleanup.
 *
 * Implemented as a strategy: RealFilesystem performs real tracking/deletion,
 * NullFilesystem is a no-op used when generated temp files should be kept.
 */
interface FilesystemInterface
{
    /**
     * Registers a file path to be deleted on the next clearAll() call.
     */
    public function addFile(string $file): void;

    /**
     * Deletes every previously registered file and resets the tracking state.
     */
    public function clearAll(): void;
}
