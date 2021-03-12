<?php

declare(strict_types=1);

namespace PhelTest\Integration\Util;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\SplFileInfo;

final class DirectoryUtil
{
    public static function removeDir(string $target): void
    {
        if (!is_dir($target)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (is_dir($file->getPathname())) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($target);
    }
}
