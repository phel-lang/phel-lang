<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use Override;

use function count;
use function is_dir;
use function is_writable;
use function sprintf;

final readonly class BuildHealthCheck implements ModuleHealthCheckInterface
{
    /**
     * @param list<string> $sourceDirectories
     */
    public function __construct(
        private string $cacheDir,
        private string $outputDirectory,
        private array $sourceDirectories,
    ) {}

    #[Override]
    public function getModuleName(): string
    {
        return 'Build';
    }

    #[Override]
    public function checkHealth(): HealthStatus
    {
        $missing = [];
        foreach ($this->sourceDirectories as $dir) {
            if (!is_dir($dir)) {
                $missing[] = $dir;
            }
        }

        if ($missing !== []) {
            return HealthStatus::degraded(
                sprintf('Source directories missing: %s', implode(', ', $missing)),
                ['missing' => $missing, 'configured' => $this->sourceDirectories],
            );
        }

        if (is_dir($this->cacheDir) && !is_writable($this->cacheDir)) {
            return HealthStatus::unhealthy(
                sprintf('Cache dir not writable: %s', $this->cacheDir),
                ['path' => $this->cacheDir],
            );
        }

        if (is_dir($this->outputDirectory) && !is_writable($this->outputDirectory)) {
            return HealthStatus::unhealthy(
                sprintf('Output dir not writable: %s', $this->outputDirectory),
                ['path' => $this->outputDirectory],
            );
        }

        return HealthStatus::healthy(
            sprintf('%d source dir(s) reachable; cache + output writable', count($this->sourceDirectories)),
            [
                'cache' => $this->cacheDir,
                'output' => $this->outputDirectory,
                'sources' => $this->sourceDirectories,
            ],
        );
    }
}
