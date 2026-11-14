<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Closure;
use FilesystemIterator;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Override;
use Phel\Shared\Exceptions\FileException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function is_writable;
use function mkdir;
use function sprintf;

/**
 * Gacela module health check for the temp directory.
 *
 * Reports healthy only when the temp directory exists and is writable. The
 * directory is auto-created on first check (idempotently), mirroring
 * TempDirFinder's creation logic. It is kept separate from TempDirFinder so a
 * health probe can run without caching the resolved path on a finder instance.
 *
 * @internal
 */
final readonly class TempDirHealthCheck implements ModuleHealthCheckInterface
{
    private const int EVAL_FILE_LIMIT = 1000;

    /** @var Closure(string): void */
    private Closure $closeToOthers;

    /**
     * @param ?Closure(string): void $closeToOthers
     */
    public function __construct(
        private string $tempDir,
        ?Closure $closeToOthers = null,
    ) {
        $this->closeToOthers = $closeToOthers ?? TempDirPolicy::closeToOthers(...);
    }

    #[Override]
    public function getModuleName(): string
    {
        return 'Filesystem';
    }

    #[Override]
    public function checkHealth(): HealthStatus
    {
        if (!is_dir($this->tempDir) && (!@mkdir($this->tempDir, 0o700, true) && !is_dir($this->tempDir))) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir could not be created: %s', $this->tempDir),
                ['path' => $this->tempDir],
            );
        }

        $violation = TempDirPolicy::violation($this->tempDir);
        if ($violation instanceof FileException) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir is unsafe. %s', $violation->getMessage()),
                ['path' => $this->tempDir],
            );
        }

        if (TempDirPolicy::isOpenToOthers($this->tempDir)) {
            ($this->closeToOthers)($this->tempDir);
        }

        // Re-read the mode: some mounts accept chmod and ignore it.
        if (TempDirPolicy::isWritableByOthers($this->tempDir)) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir is unsafe. Other users can replace the generated PHP in it: %s (chmod 700 it)', $this->tempDir),
                ['path' => $this->tempDir],
            );
        }

        if (TempDirPolicy::isOpenToOthers($this->tempDir)) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir holds generated PHP, but other users can open it: %s (chmod 700 it)', $this->tempDir),
                ['path' => $this->tempDir],
            );
        }

        if (!is_writable($this->tempDir)) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir is not writable: %s', $this->tempDir),
                ['path' => $this->tempDir],
            );
        }

        $evalFiles = $this->evalFileCount();
        if ($evalFiles > self::EVAL_FILE_LIMIT) {
            return HealthStatus::degraded(
                sprintf('Temp dir holds more than %d evaluated PHP files. Run phel cache:clear to reclaim them: %s', self::EVAL_FILE_LIMIT, $this->tempDir),
                ['path' => $this->tempDir, 'eval_files_at_least' => $evalFiles],
            );
        }

        return HealthStatus::healthy(
            sprintf('Temp dir is writable: %s', $this->tempDir),
            ['path' => $this->tempDir],
        );
    }

    private function evalFileCount(): int
    {
        if (!is_readable($this->tempDir)) {
            return 0;
        }

        $count = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
            flags: RecursiveIteratorIterator::CATCH_GET_CHILD,
        );
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $name = $file->getFilename();
            if ($file->isFile() && str_starts_with($name, '__phel_') && str_ends_with($name, '.php')) {
                if (++$count > self::EVAL_FILE_LIMIT) {
                    break;
                }
            }
        }

        return $count;
    }
}
