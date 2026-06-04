<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Override;

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
 */
final readonly class TempDirHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(
        private string $tempDir,
    ) {}

    #[Override]
    public function getModuleName(): string
    {
        return 'Filesystem';
    }

    #[Override]
    public function checkHealth(): HealthStatus
    {
        if (!is_dir($this->tempDir) && (!@mkdir($this->tempDir, 0777, true) && !is_dir($this->tempDir))) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir could not be created: %s', $this->tempDir),
                ['path' => $this->tempDir],
            );
        }

        if (!is_writable($this->tempDir)) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir is not writable: %s', $this->tempDir),
                ['path' => $this->tempDir],
            );
        }

        return HealthStatus::healthy(
            sprintf('Temp dir is writable: %s', $this->tempDir),
            ['path' => $this->tempDir],
        );
    }
}
