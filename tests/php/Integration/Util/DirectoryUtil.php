<?php

declare(strict_types=1);

namespace PhelTest\Integration\Util;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\SplFileInfo;

use function dirname;
use function strlen;

final class DirectoryUtil
{
    public static function copyPath(string $from, string $to): void
    {
        if (!is_dir($from)) {
            @mkdir(dirname($to), 0o777, true);
            copy($from, $to);

            return;
        }

        @mkdir($to, 0o777, true);
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            $dest = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
            if (is_dir($item->getPathname())) {
                @mkdir($dest, 0o777, true);
            } else {
                @mkdir(dirname($dest), 0o777, true);
                copy($item->getPathname(), $dest);
            }
        }
    }

    public static function removeDir(string $target): void
    {
        if (!is_dir($target)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
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

        rmdir($target);
    }
}
