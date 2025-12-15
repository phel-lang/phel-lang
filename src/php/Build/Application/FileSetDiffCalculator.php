<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Graph\FileSetDiff;
use Phel\Build\Domain\Graph\FileSetSnapshot;

final readonly class FileSetDiffCalculator
{
    /**
     * Calculate the difference between a cached file set and current files.
     *
     * @param array<string, int> $currentFiles path => mtime
     */
    public function calculate(?FileSetSnapshot $cached, array $currentFiles): FileSetDiff
    {
        if (!$cached instanceof FileSetSnapshot) {
            // No cache - all files are "added"
            return new FileSetDiff(
                added: array_keys($currentFiles),
                modified: [],
                deleted: [],
            );
        }

        $added = [];
        $modified = [];
        $deleted = [];

        // Find added and modified files
        foreach ($currentFiles as $file => $mtime) {
            if (!$cached->hasFile($file)) {
                $added[] = $file;
            } elseif ($cached->getMtime($file) !== $mtime) {
                $modified[] = $file;
            }
        }

        // Find deleted files
        foreach (array_keys($cached->files) as $file) {
            if (!isset($currentFiles[$file])) {
                $deleted[] = $file;
            }
        }

        return new FileSetDiff($added, $modified, $deleted);
    }
}
