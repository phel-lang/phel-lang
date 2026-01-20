<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Port\FileDiscovery;

/**
 * Driven port for discovering Phel source files in directories.
 * Abstracts file system operations for locating .phel files.
 */
interface PhelFileDiscoveryPort
{
    /**
     * Find all Phel files in the given directories.
     *
     * @param list<string> $directories
     *
     * @return list<string> Resolved absolute paths to Phel files
     */
    public function findPhelFiles(array $directories): array;

    /**
     * Resolve a path to its canonical form.
     * Handles PHAR paths and regular file system paths.
     *
     * @return string|null The resolved path or null if it cannot be resolved
     */
    public function resolvePath(string $path): ?string;
}
