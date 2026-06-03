<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

/**
 * Null-object implementation of FilesystemInterface.
 *
 * Selected by FilesystemFactory when KEEP_GENERATED_TEMP_FILES is true: both
 * addFile() and clearAll() are no-ops so generated temp files are never tracked
 * or deleted. Paired with RealFilesystem as the active-cleanup strategy.
 */
final class NullFilesystem implements FilesystemInterface
{
    public function addFile(string $file): void {}

    public function clearAll(): void {}
}
