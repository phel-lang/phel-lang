<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\FileCreator;

use Phel\Interop\Domain\Port\FileSystem\FileSystemPort;
use Phel\Interop\Domain\ReadModel\Wrapper;

use function dirname;

final readonly class FileCreator implements FileCreatorInterface
{
    public function __construct(
        private string $destinationDir,
        private FileSystemPort $fileSystem,
    ) {
    }

    public function createFromWrapper(Wrapper $wrapper): void
    {
        $wrapperPath = $this->destinationDir . '/' . $wrapper->relativeFilenamePath();
        $dir = dirname($wrapperPath);

        if (!is_dir($dir)) {
            $this->fileSystem->createDirectory($dir);
        }

        $this->fileSystem->filePutContents($wrapperPath, $wrapper->compiledPhp());
    }
}
