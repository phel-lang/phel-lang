<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractFacade;

/**
 * @method FilesystemFactory getFactory()
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
}
