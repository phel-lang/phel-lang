<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Filesystem\Domain\DirectoryWritabilityCheckerInterface;
use Phel\Shared\Exceptions\FileException;

use function function_exists;

/**
 * Resolves the configured temp directory, creating it if missing and ensuring
 * it is writable.
 *
 * The resolved path is cached on the instance after the first successful call,
 * so subsequent calls return immediately without touching the filesystem.
 * Creation is idempotent (a concurrently-created directory is tolerated).
 *
 * The directory holds generated PHP that is `require`d, so it belongs to the
 * current user alone: it is created 0700, a world-writable one we own is
 * tightened to 0700, and one another user owns is refused, since whoever owns
 * it could swap the code we run.
 *
 * @internal
 */
final class TempDirFinder
{
    private string $cachedTempDir = '';

    public function __construct(
        private readonly DirectoryWritabilityCheckerInterface $fileIo,
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
        $this->ensureOwnedByCurrentUser($tempDir);

        // Directory exists but is not writable: give its owner write access
        // and re-check before failing.
        if (!$this->fileIo->isWritable($tempDir)) {
            @chmod($tempDir, 0o700);

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
     * between the mkdir attempt and the is_dir re-check) is tolerated. A
     * restrictive umask can only narrow 0700, never widen it.
     *
     * @throws FileException if the directory cannot be created
     */
    private function ensureDirectoryExists(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            return;
        }

        // Suppressed: the thrown FileException is the user-facing signal; the
        // raw PHP warning would just duplicate it as noise above the error.
        if (!@mkdir($tempDir, 0o700, true) && !is_dir($tempDir)) {
            throw FileException::canNotCreateDirectory($tempDir);
        }
    }

    /**
     * @throws FileException if another user owns the directory
     */
    private function ensureOwnedByCurrentUser(string $tempDir): void
    {
        // Windows has no POSIX owners; its temp dir is per user already.
        if (!function_exists('posix_geteuid')) {
            return;
        }

        if (@fileowner($tempDir) !== posix_geteuid()) {
            throw FileException::directoryIsOwnedByAnotherUser($tempDir);
        }

        if ((@fileperms($tempDir) & 0o077) !== 0) {
            @chmod($tempDir, 0o700);
        }
    }
}
