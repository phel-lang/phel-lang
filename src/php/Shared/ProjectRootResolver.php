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
 * working directory: walk up from it to the nearest project root, and fall back
 * to the working directory itself (where zero-config auto-detection runs).
 *
 * A directory is a project root if it holds a `phel-config.php` (a configured
 * project, possibly without a local `vendor/` — the #2640 case) or an installed
 * `vendor/autoload.php`. The latter boundary stops the walk from escaping a
 * zero-config project nested inside another (e.g. an example project that lives
 * inside this repo) and climbing into the parent's `phel-config.php`.
 */
final class ProjectRootResolver
{
    private const string CONFIG_FILE_NAME = 'phel-config.php';

    private const string AUTOLOAD_PATH = 'vendor/autoload.php';

    public static function resolveFromCwd(string $cwd): string
    {
        $dir = $cwd;
        while (true) {
            if (is_file($dir . '/' . self::CONFIG_FILE_NAME)
                || is_file($dir . '/' . self::AUTOLOAD_PATH)
            ) {
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
