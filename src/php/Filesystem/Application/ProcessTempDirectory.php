<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use DirectoryIterator;
use OutOfBoundsException;
use Phel\Shared\Exceptions\FileException;

use function dirname;
use function strlen;

/**
 * @phpstan-type Lease array{path: string, lock: resource, pid: int, keep: bool}
 *
 * @internal
 */
final class ProcessTempDirectory
{
    private const int SWEEP_ENTRIES = 64;

    private const int SWEEP_FILES = 2048;

    /** @var array<string, Lease> */
    private static array $leases = [];

    private static ?int $leasePid = null;

    public function __construct(
        private readonly TempDirFinder $tempDirFinder,
        private readonly bool $keepGeneratedFiles,
    ) {}

    public function getOrCreate(): string
    {
        $pid = getmypid() ?: 0;
        $root = $this->tempDirFinder->getOrCreateTempDir() . DIRECTORY_SEPARATOR . 'eval';
        $key = $root . '|' . $pid . '|' . (int) $this->keepGeneratedFiles;
        $this->closeInheritedLeases($pid);
        $lease = self::$leases[$key] ?? null;
        if ($lease !== null && is_dir($lease['path'])) {
            return $lease['path'];
        }

        $root = new TempDirFinder(new FileIo(), $root)->getOrCreateTempDir();
        $guard = $this->openLock($root . DIRECTORY_SEPARATOR . '.sweep.lock');
        if (!flock($guard, LOCK_EX)) {
            fclose($guard);
            throw FileException::canNotCreateFile($root . DIRECTORY_SEPARATOR . '.sweep.lock');
        }

        try {
            $this->sweep($root, $guard);
            $path = $root . DIRECTORY_SEPARATOR . '__phel_' . $pid . '_' . bin2hex(random_bytes(16));
            if (!@mkdir($path, 0o700)) {
                throw FileException::canNotCreateDirectory($path);
            }

            $lock = $this->openLock($path . DIRECTORY_SEPARATOR . '.lock');
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                fclose($lock);
                throw FileException::canNotCreateFile($path . DIRECTORY_SEPARATOR . '.lock');
            }

            if ($this->keepGeneratedFiles && @file_put_contents($path . DIRECTORY_SEPARATOR . '.keep', '') === false) {
                fclose($lock);
                throw FileException::canNotCreateFile($path . DIRECTORY_SEPARATOR . '.keep');
            }

            self::$leases[$key] = ['path' => $path, 'lock' => $lock, 'pid' => $pid, 'keep' => $this->keepGeneratedFiles];
            register_shutdown_function(static function () use ($key, $pid): void {
                if ((getmypid() ?: 0) === $pid) {
                    self::release($key);
                }
            });

            return $path;
        } finally {
            fclose($guard);
        }
    }

    public static function clearAll(): void
    {
        $pid = getmypid() ?: 0;
        foreach (self::$leases as $key => $lease) {
            if ($lease['pid'] === $pid && !$lease['keep']) {
                self::release($key);
            }
        }
    }

    private function closeInheritedLeases(int $pid): void
    {
        if (self::$leasePid === $pid) {
            return;
        }

        self::$leasePid = $pid;
        foreach (self::$leases as $key => $lease) {
            if ($lease['pid'] !== $pid) {
                // Closing a fork's descriptor leaves the parent's lock held.
                fclose($lease['lock']);
                unset(self::$leases[$key]);
            }
        }
    }

    /**
     * @return resource
     */
    private function openLock(string $path)
    {
        $lock = @fopen($path, 'c+b');
        if ($lock === false) {
            throw FileException::canNotCreateFile($path);
        }

        return $lock;
    }

    /**
     * @param resource $guard
     */
    private function sweep(string $root, $guard): void
    {
        rewind($guard);
        $storedCursor = stream_get_contents($guard, 32);
        $cursor = (int) $storedCursor;
        if ($cursor < 0 || $storedCursor !== $cursor . "\n") {
            $cursor = 0;
        }

        $entry = new DirectoryIterator($root);
        try {
            $entry->seek($cursor);
        } catch (OutOfBoundsException) {
            $entry->rewind();
            $cursor = 0;
        }

        if (!$entry->valid()) {
            $entry->rewind();
            $cursor = 0;
        }

        $files = self::SWEEP_FILES;
        for ($inspected = 0; $entry->valid() && $inspected < self::SWEEP_ENTRIES && $files > 0; ++$inspected, ++$cursor, $entry->next()) {
            if (!$entry->isDir() || $entry->isLink() || preg_match('/^__phel_[0-9]+_[a-f0-9]{32}$/D', $entry->getFilename()) !== 1) {
                continue;
            }

            $path = $entry->getPathname();
            if (file_exists($path . DIRECTORY_SEPARATOR . '.keep')) {
                continue;
            }

            $lockPath = $path . DIRECTORY_SEPARATOR . '.lock';
            if (is_link($lockPath)) {
                continue;
            }

            $lock = @fopen($lockPath, 'r+b');
            if ($lock === false) {
                // The creation guard proves no writer is between mkdir and lock.
                @rmdir($path);
                continue;
            }

            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                fclose($lock);
                continue;
            }

            $empty = self::removeFiles($path, $files);
            fclose($lock);
            if ($empty) {
                @unlink($lockPath);
                @rmdir($path);
            }
        }

        $nextCursor = ($entry->valid() ? $cursor : 0) . "\n";
        rewind($guard);
        if (!ftruncate($guard, 0) || fwrite($guard, $nextCursor) !== strlen($nextCursor)) {
            throw FileException::canNotCreateFile($root . DIRECTORY_SEPARATOR . '.sweep.lock');
        }

        fflush($guard);
    }

    private static function release(string $key): void
    {
        $lease = self::$leases[$key] ?? null;
        if ($lease === null) {
            return;
        }

        unset(self::$leases[$key]);
        if ($lease['keep']) {
            fclose($lease['lock']);
            return;
        }

        $files = PHP_INT_MAX;
        $empty = is_dir($lease['path']) && self::removeFiles($lease['path'], $files);
        $guard = @fopen(dirname($lease['path']) . DIRECTORY_SEPARATOR . '.sweep.lock', 'c+b');
        $ownsGuard = $guard !== false && flock($guard, LOCK_EX | LOCK_NB);
        fclose($lease['lock']);
        if ($empty && $ownsGuard) {
            @unlink($lease['path'] . DIRECTORY_SEPARATOR . '.lock');
            @rmdir($lease['path']);
        }

        if ($guard !== false) {
            fclose($guard);
        }
    }

    private static function removeFiles(string $path, int &$budget): bool
    {
        $empty = true;
        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot() || $entry->getFilename() === '.lock') {
                continue;
            }

            if ($budget === 0) {
                return false;
            }

            --$budget;
            if (!$entry->isDir() && preg_match('/^__phel_[a-f0-9]{32}\.php$/D', $entry->getFilename()) === 1) {
                $empty = @unlink($entry->getPathname()) && $empty;
            } else {
                $empty = false;
            }
        }

        return $empty;
    }
}
