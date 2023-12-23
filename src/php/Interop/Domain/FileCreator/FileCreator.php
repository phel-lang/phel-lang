<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\FileCreator;

use Phel\Interop\Domain\ReadModel\Wrapper;

use function dirname;

final readonly class FileCreator implements FileCreatorInterface
{
    public function __construct(
        private string $destinationDir,
        private FileIoInterface $io,
    ) {
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
