<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use FilesystemIterator;
use Phel\Run\Domain\Test\WatchFileScannerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Polling-friendly snapshot of the `.phel` sources (plus `phel-config.php`
 * files) under a set of directories. Two consecutive snapshots compare equal
 * exactly when no watched file was added, removed, or modified in between.
 */
final class WatchFileScanner implements WatchFileScannerInterface
{
    public function snapshot(array $directories): array
    {
        $snapshot = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $name = $file->getFilename();
                if (!str_ends_with($name, '.phel') && $name !== 'phel-config.php') {
                    continue;
                }

                $mtime = $file->getMTime();
                $snapshot[$file->getPathname()] = $mtime;
            }
        }

        ksort($snapshot);

        return $snapshot;
    }
}
