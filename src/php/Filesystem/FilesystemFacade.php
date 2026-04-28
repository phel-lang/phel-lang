<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

/**
 * @extends AbstractFacade<FilesystemFactory>
 */
final class FilesystemFacade extends AbstractFacade implements FilesystemFacadeInterface
{
    public function addFile(string $file): void
    {
        $this->getFactory()
            ->createFilesystem()
            ->addFile($file);
    }

    public function clearAll(): void
    {
        $this->getFactory()
            ->createFilesystem()
            ->clearAll();
    }

    public function getTempDir(): string
    {
        return $this->getFactory()
            ->createTempDirFinder()
            ->getOrCreateTempDir();
    }

    public function getHealthCheck(): ModuleHealthCheckInterface
    {
        return $this->getFactory()->createTempDirHealthCheck();
    }
}
