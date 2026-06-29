<?php

declare(strict_types=1);

namespace Phel\Shared;

use function dirname;
use function is_file;

/**
 * Discovers the project root for a CLI invocation independently of where the
 * `phel` binary (and its autoloader) live.
 *
 * A global Composer or PHAR install puts the binary — and the autoloader the
 * launcher walks up to find — outside the user's project. Anchoring the root to
 * that location makes `phel config`/`doctor` read the wrong `phel-config.php`
 * and check the wrong source/test dirs (#2640). Instead we anchor to the
 * working directory: walk up from it to the nearest `phel-config.php`, and fall
 * back to the working directory itself (where zero-config auto-detection runs).
 */
final class ProjectRootResolver
{
    private const string CONFIG_FILE_NAME = 'phel-config.php';

    public static function resolveFromCwd(string $cwd): string
    {
        $dir = $cwd;
        while (true) {
            if (is_file($dir . '/' . self::CONFIG_FILE_NAME)) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return $cwd;
            }

            $dir = $parent;
        }
    }
}
