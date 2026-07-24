<?php

declare(strict_types=1);

namespace PhelTest\Support;

use function is_dir;
use function is_link;
use function rmdir;
use function scandir;
use function unlink;

/**
 * Recursive directory removal for test fixtures and temp dirs.
 *
 * Symlinked directories are unlinked rather than descended into, so a fixture
 * that links out of its own tree can never delete anything outside it. Removal
 * failures are ignored: this runs in `tearDown()`, where a leftover file must
 * not mask the assertion that actually failed.
 */
trait RemoveDirTrait
{
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
