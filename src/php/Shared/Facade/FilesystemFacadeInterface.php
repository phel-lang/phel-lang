<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Gacela\Framework\Health\ModuleHealthCheckInterface;

interface FilesystemFacadeInterface
{
    public function addFile(string $file): void;

    public function clearAll(): void;

    public function getTempDir(): string;

    public function getHealthCheck(): ModuleHealthCheckInterface;
}
