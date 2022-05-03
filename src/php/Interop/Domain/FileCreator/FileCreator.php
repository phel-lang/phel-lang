<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\FileCreator;

use Phel\Interop\Domain\ReadModel\Wrapper;

use function dirname;

final class FileCreator implements FileCreatorInterface
{
    private string $destinationDir;

    private FileIoInterface $io;

    public function __construct(string $destinationDir, FileIoInterface $io)
    {
        $this->destinationDir = $destinationDir;
        $this->io = $io;
    }

    public function createFromWrapper(Wrapper $wrapper): void
    {
        $wrapperPath = $this->destinationDir . '/' . $wrapper->relativeFilenamePath();
        $dir = dirname($wrapperPath);

        if (!is_dir($dir)) {
            $this->io->createDirectory($dir);
        }

        $this->io->filePutContents($wrapperPath, $wrapper->compiledPhp());
    }
}