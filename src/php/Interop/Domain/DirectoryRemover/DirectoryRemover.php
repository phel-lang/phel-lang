<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\DirectoryRemover;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\SplFileInfo;

final readonly class DirectoryRemover implements DirectoryRemoverInterface
{
    public function __construct(private string $targetDir)
    {
    }

    public function removeDir(): void
    {
        if (!is_dir($this->targetDir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->targetDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (is_dir($file->getPathname())) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->targetDir);
    }
}
