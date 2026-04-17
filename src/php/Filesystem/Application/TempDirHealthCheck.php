<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Override;

use function is_dir;
use function is_writable;
use function sprintf;

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
        if (!is_dir($this->tempDir)) {
            return HealthStatus::unhealthy(
                sprintf('Temp dir does not exist: %s', $this->tempDir),
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
